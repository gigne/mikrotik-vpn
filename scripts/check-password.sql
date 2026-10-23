SELECT id, username, LEFT(password, 20) as password_preview, LENGTH(password) as pwd_len FROM vpn_users WHERE username='mt-gigne';
SELECT id, vpn_username, LEFT(vpn_password_encrypted, 20) as pwd_enc_preview, LENGTH(vpn_password_encrypted) as pwd_enc_len FROM mikrotiks WHERE vpn_username='mt-gigne';
