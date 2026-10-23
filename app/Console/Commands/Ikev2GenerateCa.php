<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Generate a self-signed CA certificate and key for signing IKEv2 client certs.
 *
 * This is required only once per VPN server. The resulting CA cert + key are
 * used by Ikev2CertificateService to sign client certificates.
 */
class Ikev2GenerateCa extends Command
{
    protected $signature = 'ikev2:generate-ca
                            {--output-dir=/etc/ipsec.d/certs : output directory for CA cert/key}';

    protected $description = 'Generate a self-signed CA certificate for IKEv2 client signing';

    public function handle(): int
    {
        $dir = $this->option('output-dir');

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0700, true)) {
                $this->error("Failed to create directory: {$dir}");
                return self::FAILURE;
            }
        }

        $caCertPath = "{$dir}/ca-cert.pem";
        $caKeyPath = dirname($dir) . '/private/ca-key.pem';
        $serialFile = "{$dir}/ca-serial";

        // Check if CA already exists
        if (file_exists($caCertPath) && file_exists($caKeyPath)) {
            $this->warn("CA certificate already exists at {$caCertPath}");
            $this->warn("To regenerate, manually remove existing files first.");
            return self::SUCCESS;
        }

        $this->info("Generating CA private key...");
        $config = [
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'encrypt_key_algorithm' => OPENSSL_PKCS1_PADDING,
            'encrypt_key_password' => null, // No passphrase
        ];

        $privateKey = openssl_pkey_new($config);
        if ($privateKey === false) {
            $this->error("Failed to generate private key: " . openssl_error_string());
            return self::FAILURE;
        }

        openssl_pkey_export($privateKey, $privateKeyPem);
        
        // Save private key
        if (!file_exists(dirname($caKeyPath))) {
            mkdir(dirname($caKeyPath), 0700, true);
        }
        File::put($caKeyPath, $privateKeyPem);
        chmod($caKeyPath, 0600);
        $this->info("✓ CA private key saved to: {$caKeyPath}");

        // Generate CA certificate (self-signed)
        $this->info("Generating self-signed CA certificate...");
        $certConfig = [
            'distiguished_name' => [
                'countryName' => 'ID',
                'stateOrProvinceName' => 'Jakarta',
                'organizationName' => 'VPN Panel CA',
                'commonName' => 'VPN Panel Certificate Authority',
            ],
            'days' => 3650, // 10 years
            'digest_alg' => 'sha256',
        ];

        $csr = openssl_csr_new($certConfig['distiguished_name'], $privateKey, $certConfig);
        if ($csr === false) {
            $this->error("Failed to create CSR: " . openssl_error_string());
            return self::FAILURE;
        }

        $cert = openssl_csr_sign($csr, null, $privateKey, $certConfig['days'], $certConfig);
        if ($cert === false) {
            $this->error("Failed to sign certificate: " . openssl_error_string());
            return self::FAILURE;
        }

        openssl_x509_export($cert, $certPem);
        File::put($caCertPath, $certPem);
        $this->applyPermissions($dir, $caCertPath, $caKeyPath, $serialFile);
        $this->info("✓ CA certificate saved to: {$caCertPath}");

        // Initialize serial file
        File::put($serialFile, '1000');
        $this->applyPermissions($dir, $caCertPath, $caKeyPath, $serialFile);
        $this->info("✓ Serial file created at: {$serialFile}");

        $this->info("\nCA certificate generation completed!");
        $this->info("\nNext steps:");
        $this->info("  - Copy ca-cert.pem to client devices or use mobileconfig profile");
        $this->info("  - The CA will be embedded in generated iOS/macOS profiles");

        return self::SUCCESS;
    }

    /**
     * Grant the panel process read access to the CA cert/key and write access
     * to the serial file without making the private key world-readable.
     */
    private function applyPermissions(string $dir, string $caCertPath, string $caKeyPath, string $serialFile): void
    {
        $privateDir = dirname($caKeyPath);

        @chmod($dir, 0755);
        @chmod($privateDir, 0750);
        @chmod($caCertPath, 0644);
        @chmod($caKeyPath, 0640);
        @chmod($serialFile, 0664);

        $panelGroup = $this->detectPanelGroup();

        if ($panelGroup === null) {
            return;
        }

        @chgrp($privateDir, $panelGroup);
        @chgrp($caKeyPath, $panelGroup);
        @chgrp($serialFile, $panelGroup);
    }

    /**
     * Detect the group of the web process from the storage directory owner.
     */
    private function detectPanelGroup(): ?string
    {
        $storagePath = storage_path();

        if (!file_exists($storagePath)) {
            return null;
        }

        $groupId = filegroup($storagePath);

        if ($groupId === false) {
            return null;
        }

        $group = function_exists('posix_getgrgid') ? posix_getgrgid($groupId) : false;

        return $group['name'] ?? null;
    }
}
