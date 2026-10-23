<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ikev2User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Generates and manages IKEv2 certificates using OpenSSL.
 */
class Ikev2CertificateService
{
    private readonly string $caCertPath;
    private readonly string $caKeyPath;
    private readonly string $caSerialFile;

    public function __construct()
    {
        $this->caCertPath = config('vpnpanel.ikev2.ca_cert_path', '/etc/ipsec.d/certs/ca-cert.pem');
        $this->caKeyPath = config('vpnpanel.ikev2.ca_key_path', '/etc/ipsec.d/private/ca-key.pem');
        $this->caSerialFile = config('vpnpanel.ikev2.ca_serial_path', '/etc/ipsec.d/certs/ca-serial');
    }

    /**
     * Generate client certificate and key for an IKEv2 user.
     *
     * @return array{certificate_pem: string, private_key_pem: string, certificate_serial: string, ca_certificate_pem: string}
     */
    public function generateClientCertificate(Ikev2User $user): array
    {
        $cn = $user->username;
        $days = $user->expired_at
            ? max(1, $user->expired_at->diffInDays(now()))
            : 365;

        // Generate private key
        $privateKey = $this->generatePrivateKey();

        // Generate CSR
        $csr = $this->generateCsr($privateKey, $cn);

        // Sign certificate dengan CA
        $certificate = $this->signCertificate($csr, $privateKey, $days);

        // Extract serial number
        $serial = $this->extractSerialNumber($certificate);

        // Get CA certificate
        $caCert = $this->getCACertificate();

        return [
            'certificate_pem' => $certificate,
            'private_key_pem' => $privateKey,
            'certificate_serial' => $serial,
            'ca_certificate_pem' => $caCert,
        ];
    }

    /**
     * Generate RSA private key (2048 bit).
     */
    private function generatePrivateKey(): string
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $privateKey = openssl_pkey_new($config);
        
        if ($privateKey === false) {
            throw new \RuntimeException('Failed to generate private key: ' . openssl_error_string());
        }

        if (!openssl_pkey_export($privateKey, $privateKeyPem)) {
            throw new \RuntimeException('Failed to export private key: ' . openssl_error_string());
        }

        return $privateKeyPem;
    }

    /**
     * Generate Certificate Signing Request.
     */
    private function generateCsr(string $privateKey, string $commonName): string
    {
        $dn = [
            'CN' => $commonName,
        ];

        $csr = openssl_csr_new($dn, $privateKey, ['digest_alg' => 'sha256']);
        
        if ($csr === false) {
            throw new \RuntimeException('Failed to generate CSR: ' . openssl_error_string());
        }

        if (!openssl_csr_export($csr, $csrPem)) {
            throw new \RuntimeException('Failed to export CSR: ' . openssl_error_string());
        }

        return $csrPem;
    }

    /**
     * Sign certificate with CA.
     */
    private function signCertificate(string $csr, string $privateKey, int $days): string
    {
        if (!is_readable($this->caCertPath)) {
            throw new \RuntimeException("CA certificate is not readable at {$this->caCertPath}");
        }

        if (!is_readable($this->caKeyPath)) {
            throw new \RuntimeException("CA private key is not readable at {$this->caKeyPath}");
        }

        $caCert = openssl_x509_read(file_get_contents($this->caCertPath));
        $caKey = openssl_pkey_get_private(file_get_contents($this->caKeyPath));

        if ($caCert === false || $caKey === false) {
            throw new \RuntimeException('Failed to read CA certificate or key: ' . openssl_error_string());
        }

        $serial = $this->nextAvailableSerial();

        $certificate = openssl_csr_sign(
            $csr,
            $caCert,
            $caKey,
            $days,
            [
                'digest_alg' => 'sha256',
                'x509_extensions' => 'usr_cert',
            ],
            $serial
        );

        if ($certificate === false) {
            throw new \RuntimeException('Failed to sign certificate: ' . openssl_error_string());
        }

        openssl_x509_export($certificate, $certPem);

        $this->saveSerial($serial + 1);

        return $certPem;
    }

    /**
     * Allocate a serial that is unique both in the serial file and in the DB.
     *
     * The serial file can be stale (for example when the panel process cannot
     * write to /etc/ipsec.d), so the database is treated as the source of
     * truth for already issued certificates.
     */
    private function nextAvailableSerial(): int
    {
        $serial = $this->getNextSerial();

        while (Ikev2User::withTrashed()->where('certificate_serial', (string) $serial)->exists()) {
            $serial++;
        }

        return $serial;
    }

    /**
     * Get next serial number from file.
     */
    private function getNextSerial(): int
    {
        if (is_readable($this->caSerialFile)) {
            $serial = trim((string) file_get_contents($this->caSerialFile));

            if ($serial !== '' && ctype_xdigit($serial)) {
                return hexdec($serial);
            }
        }

        // Fall back to a random serial so certificates never collide even when
        // the serial file is missing or unreadable.
        return random_int(1, 0x7FFFFFFF);
    }

    /**
     * Save serial number to file.
     */
    private function saveSerial(int $serial): void
    {
        $dir = dirname($this->caSerialFile);

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        if (is_file($this->caSerialFile) && !is_writable($this->caSerialFile)) {
            Log::warning("IKEv2 serial file is not writable: {$this->caSerialFile}");
            return;
        }

        $written = @file_put_contents($this->caSerialFile, sprintf('%08X', $serial));

        if ($written === false) {
            Log::warning("Failed to persist IKEv2 serial to {$this->caSerialFile}");
        }
    }

    /**
     * Extract serial number from certificate.
     */
    private function extractSerialNumber(string $certificate): string
    {
        $info = openssl_x509_parse($certificate);
        
        if ($info === false || !isset($info['serialNumber'])) {
            throw new \RuntimeException('Failed to extract serial number');
        }

        return $info['serialNumber'];
    }

    /**
     * Get CA certificate.
     */
    private function getCACertificate(): string
    {
        if (!file_exists($this->caCertPath)) {
            throw new \RuntimeException("CA certificate not found at {$this->caCertPath}");
        }

        return file_get_contents($this->caCertPath);
    }

    /**
     * Generate .mobileconfig profile untuk iOS/macOS.
     */
    public function generateMobileConfig(Ikev2User $user, string $serverAddress): string
    {
        $uuid = strtoupper((string) Str::uuid());
        
        $mobileConfig = '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>PayloadContent</key>
    <array>
        <dict>
            <key>IKEv2</key>
            <dict>
                <key>AuthenticationMethod</key>
                <string>Certificate</string>
                <key>ChildSecurityAssociationParameters</key>
                <dict>
                    <key>DiffieHellmanGroup</key>
                    <integer>14</integer>
                    <key>EncryptionAlgorithm</key>
                    <string>AES-256-GCM</string>
                    <key>LifeTimeInMinutes</key>
                    <integer>1410</integer>
                </dict>
                <key>DeadPeerDetectionRate</key>
                <string>Medium</string>
                <key>DisableRedirect</key>
                <true/>
                <key>EnableCertificateRevocationCheck</key>
                <integer>0</integer>
                <key>EnablePFS</key>
                <integer>0</integer>
                <key>IKESecurityAssociationParameters</key>
                <dict>
                    <key>DiffieHellmanGroup</key>
                    <integer>14</integer>
                    <key>EncryptionAlgorithm</key>
                    <string>AES-256-GCM</string>
                    <key>IntegrityAlgorithm</key>
                    <string>SHA2-256</string>
                    <key>LifeTimeInMinutes</key>
                    <integer>1410</integer>
                </dict>
                <key>LocalIdentifier</key>
                <string>' . htmlspecialchars($user->username) . '</string>
                <key>PayloadCertificateUUID</key>
                <string>' . $uuid . '-CERT</string>
                <key>OnDemandEnabled</key>
                <integer>0</integer>
                <key>OnDemandRules</key>
                <array>
                    <dict>
                        <key>InterfaceTypeMatch</key>
                        <string>WiFi</string>
                        <key>URLStringProbe</key>
                        <string>http://captive.apple.com/hotspot-detect.html</string>
                        <key>Action</key>
                        <string>Connect</string>
                    </dict>
                </array>
                <key>RemoteAddress</key>
                <string>' . htmlspecialchars($serverAddress) . '</string>
                <key>RemoteIdentifier</key>
                <string>' . htmlspecialchars($serverAddress) . '</string>
                <key>UseConfigurationAttributeInternalIPSourceAddress</key>
                <integer>0</integer>
            </dict>
            <key>IPv4</key>
            <dict>
                <key>OverridePrimary</key>
                <integer>1</integer>
            </dict>
            <key>PayloadDescription</key>
            <string>Configures VPN settings</string>
            <key>PayloadDisplayName</key>
            <string>VPN</string>
            <key>PayloadIdentifier</key>
            <string>com.apple.vpn.managed.' . Str::uuid() . '</string>
            <key>PayloadType</key>
            <string>com.apple.vpn.managed</string>
            <key>PayloadUUID</key>
            <string>' . $uuid . '-VPN</string>
            <key>PayloadVersion</key>
            <integer>1</integer>
            <key>Proxies</key>
            <dict>
                <key>HTTPEnable</key>
                <integer>0</integer>
                <key>HTTPSEnable</key>
                <integer>0</integer>
            </dict>
            <key>UserDefinedName</key>
            <string>' . htmlspecialchars($user->username . ' IKEv2') . '</string>
            <key>VPNType</key>
            <string>IKEv2</string>
        </dict>
        <dict>
            <key>PayloadCertificateFileName</key>
            <string>' . htmlspecialchars($user->username) . '</string>
            <key>PayloadContent</key>
            <data>' . base64_encode($user->private_key_pem) . '</data>
            <key>PayloadDescription</key>
            <string>VPN Private Key</string>
            <key>PayloadIdentifier</key>
            <string>com.apple.security.pkcs1.' . Str::uuid() . '</string>
            <key>PayloadType</key>
            <string>com.apple.security.pkcs1</string>
            <key>PayloadUUID</key>
            <string>' . $uuid . '-CERT</string>
            <key>PayloadVersion</key>
            <integer>1</integer>
        </dict>
        <dict>
            <key>PayloadCertificateFileName</key>
            <string>CA Certificate</string>
            <key>PayloadContent</key>
            <data>' . base64_encode($user->ca_certificate_pem) . '</data>
            <key>PayloadDescription</key>
            <string>VPN CA Certificate</string>
            <key>PayloadIdentifier</key>
            <string>com.apple.security.root.' . Str::uuid() . '</string>
            <key>PayloadType</key>
            <string>com.apple.security.root</string>
            <key>PayloadUUID</key>
            <string>' . $uuid . '-CA</string>
            <key>PayloadVersion</key>
            <integer>1</integer>
        </dict>
    </array>
    <key>PayloadDisplayName</key>
    <string>VPN Panel - ' . htmlspecialchars($user->username) . '</string>
    <key>PayloadIdentifier</key>
    <string>com.vpnpanel.ikev2.' . Str::uuid() . '</string>
    <key>PayloadRemovalDisallowed</key>
    <false/>
    <key>PayloadType</key>
    <string>Configuration</string>
    <key>PayloadUUID</key>
    <string>' . $uuid . '</string>
    <key>PayloadVersion</key>
    <integer>1</integer>
</dict>
</plist>';

        return $mobileConfig;
    }
}
