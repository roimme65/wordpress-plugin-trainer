#!/bin/bash
# Script zum Anlegen von 5 Trainer-Accounts

echo "Erstelle 5 Trainer-Accounts..."

# SQL-Befehle direkt in der Datenbank ausführen
docker-compose exec -T db mysql -u wordpress -pwordpress wordpress << 'EOF'

-- Trainer 1
INSERT INTO wp_users (user_login, user_pass, user_nicename, user_email, user_registered, display_name) 
VALUES ('trainer1', MD5('trainer123'), 'trainer1', 'trainer1@test.de', NOW(), 'Trainer Eins')
ON DUPLICATE KEY UPDATE user_email=user_email;

SET @user_id_1 = LAST_INSERT_ID();
INSERT INTO wp_usermeta (user_id, meta_key, meta_value) VALUES (@user_id_1, 'wp_capabilities', 'a:1:{s:10:"subscriber";b:1;}');
INSERT INTO wp_usermeta (user_id, meta_key, meta_value) VALUES (@user_id_1, 'wp_user_level', '0');

-- Trainer 2
INSERT INTO wp_users (user_login, user_pass, user_nicename, user_email, user_registered, display_name) 
VALUES ('trainer2', MD5('trainer123'), 'trainer2', 'trainer2@test.de', NOW(), 'Trainer Zwei')
ON DUPLICATE KEY UPDATE user_email=user_email;

SET @user_id_2 = LAST_INSERT_ID();
INSERT INTO wp_usermeta (user_id, meta_key, meta_value) VALUES (@user_id_2, 'wp_capabilities', 'a:1:{s:10:"subscriber";b:1;}');
INSERT INTO wp_usermeta (user_id, meta_key, meta_value) VALUES (@user_id_2, 'wp_user_level', '0');

-- Trainer 3
INSERT INTO wp_users (user_login, user_pass, user_nicename, user_email, user_registered, display_name) 
VALUES ('trainer3', MD5('trainer123'), 'trainer3', 'trainer3@test.de', NOW(), 'Trainer Drei')
ON DUPLICATE KEY UPDATE user_email=user_email;

SET @user_id_3 = LAST_INSERT_ID();
INSERT INTO wp_usermeta (user_id, meta_key, meta_value) VALUES (@user_id_3, 'wp_capabilities', 'a:1:{s:10:"subscriber";b:1;}');
INSERT INTO wp_usermeta (user_id, meta_key, meta_value) VALUES (@user_id_3, 'wp_user_level', '0');

-- Trainer 4
INSERT INTO wp_users (user_login, user_pass, user_nicename, user_email, user_registered, display_name) 
VALUES ('trainer4', MD5('trainer123'), 'trainer4', 'trainer4@test.de', NOW(), 'Trainer Vier')
ON DUPLICATE KEY UPDATE user_email=user_email;

SET @user_id_4 = LAST_INSERT_ID();
INSERT INTO wp_usermeta (user_id, meta_key, meta_value) VALUES (@user_id_4, 'wp_capabilities', 'a:1:{s:10:"subscriber";b:1;}');
INSERT INTO wp_usermeta (user_id, meta_key, meta_value) VALUES (@user_id_4, 'wp_user_level', '0');

-- Trainer 5
INSERT INTO wp_users (user_login, user_pass, user_nicename, user_email, user_registered, display_name) 
VALUES ('trainer5', MD5('trainer123'), 'trainer5', 'trainer5@test.de', NOW(), 'Trainer Fünf')
ON DUPLICATE KEY UPDATE user_email=user_email;

SET @user_id_5 = LAST_INSERT_ID();
INSERT INTO wp_usermeta (user_id, meta_key, meta_value) VALUES (@user_id_5, 'wp_capabilities', 'a:1:{s:10:"subscriber";b:1;}');
INSERT INTO wp_usermeta (user_id, meta_key, meta_value) VALUES (@user_id_5, 'wp_user_level', '0');

SELECT 'Trainer-Accounts wurden erstellt!' as Status;
SELECT ID, user_login, user_email, display_name FROM wp_users WHERE user_login LIKE 'trainer%';

EOF

echo ""
echo "✅ Fertig!"
echo ""
echo "📋 Login-Daten für alle Trainer:"
echo "================================"
echo "Benutzername: trainer1 | Passwort: trainer123"
echo "Benutzername: trainer2 | Passwort: trainer123"
echo "Benutzername: trainer3 | Passwort: trainer123"
echo "Benutzername: trainer4 | Passwort: trainer123"
echo "Benutzername: trainer5 | Passwort: trainer123"
echo ""
echo "🌐 Login-URL: http://localhost:8080/wp-login.php"
