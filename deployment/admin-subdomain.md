# Deploiement durci : espace client et espace admin

Objectif : conserver le meme code source, tout en isolant les cookies et en
interdisant l'acces HTTP aux fichiers internes (`.git`, `config`, `database`,
`deployment`, `includes`, `models`, documents SQL/DOCX/ENV, etc.).

Le code utilise deux noms de session distincts et limite aussi le cookie admin
au chemin `/admin`. Un sous-domaine admin ajoute une isolation d'origine utile.

## Variables d'environnement communes

Production :

```text
LOGITIX_APP_ENV=production
LOGITIX_FORCE_HTTPS=true
LOGITIX_ADMIN_REQUIRE_2FA=true
LOGITIX_ADMIN_BOOTSTRAP_ENABLED=false
LOGITIX_TRUST_PROXY_HTTPS=false
LOGITIX_TRUSTED_PROXY_IPS=
```

`LOGITIX_TRUST_PROXY_HTTPS=true` ne doit etre active que derriere un reverse
proxy de confiance. `LOGITIX_TRUSTED_PROXY_IPS` doit alors contenir uniquement
les IP des proxies autorises. Le proxy doit remplacer les headers
`X-Forwarded-Proto` et `X-Forwarded-For` recus depuis Internet.

## Apache

Le `.htaccess` du projet bloque deja les repertoires et extensions internes si
`AllowOverride` autorise les regles correspondantes. Pour une defense en
profondeur, les VirtualHost doivent aussi limiter chaque origine.

```apache
<VirtualHost *:80>
    ServerName www.logitix.ci
    Redirect permanent / https://www.logitix.ci/
</VirtualHost>

<VirtualHost *:443>
    ServerName www.logitix.ci
    DocumentRoot /var/www/logitix
    SetEnv LOGITIX_APP_URL https://www.logitix.ci
    SetEnv LOGITIX_APP_ENV production
    SetEnv LOGITIX_FORCE_HTTPS true
    SetEnv LOGITIX_ADMIN_REQUIRE_2FA true

    <Location /admin>
        Require all denied
    </Location>

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/www.logitix.ci/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/www.logitix.ci/privkey.pem
</VirtualHost>

<VirtualHost *:80>
    ServerName admin.logitix.ci
    Redirect permanent / https://admin.logitix.ci/
</VirtualHost>

<VirtualHost *:443>
    ServerName admin.logitix.ci
    DocumentRoot /var/www/logitix
    SetEnv LOGITIX_APP_URL https://admin.logitix.ci
    SetEnv LOGITIX_APP_ENV production
    SetEnv LOGITIX_FORCE_HTTPS true
    SetEnv LOGITIX_ADMIN_REQUIRE_2FA true

    <LocationMatch "^/(?!admin(?:/|$)|css(?:/|$)|js(?:/|$)|images(?:/|$)|fonts(?:/|$))">
        Require all denied
    </LocationMatch>

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/admin.logitix.ci/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/admin.logitix.ci/privkey.pem
</VirtualHost>
```

## Nginx

Nginx n'interprete pas `.htaccess`. Il faut donc reproduire explicitement les
interdictions et headers de securite.

```nginx
server {
    listen 80;
    server_name www.logitix.ci;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name www.logitix.ci;
    root /var/www/logitix;

    location ^~ /admin/ { deny all; }
    location ~ (^|/)\. { deny all; }
    location ~ ^/(config|database|deployment|includes|models|tools|vendor|tests)(/|$) { deny all; }
    location ~* \.(env|ini|log|sql|bak|dump|sqlite|sqlite3|docx|md|yml|yaml|dist|zip|tar|tgz|gz|rar|7z|pem|key|p12|pfx)$ { deny all; }

    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Permitted-Cross-Domain-Policies "none" always;
    add_header Cross-Origin-Opener-Policy "same-origin" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data:; font-src 'self' data: https://fonts.gstatic.com; connect-src 'self'; frame-src https://www.google.com; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'" always;

    # Configurer ici PHP-FPM en transmettant les variables LOGITIX_*.
}

server {
    listen 80;
    server_name admin.logitix.ci;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name admin.logitix.ci;
    root /var/www/logitix;

    location ~ (^|/)\. { deny all; }
    location ~ ^/(config|database|deployment|includes|models|tools|vendor|tests)(/|$) { deny all; }
    location ~* \.(env|ini|log|sql|bak|dump|sqlite|sqlite3|docx|md|yml|yaml|dist|zip|tar|tgz|gz|rar|7z|pem|key|p12|pfx)$ { deny all; }
    location ~ ^/(?!admin(?:/|$)|css(?:/|$)|js(?:/|$)|images(?:/|$)|fonts(?:/|$)) { deny all; }

    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Permitted-Cross-Domain-Policies "none" always;
    add_header Cross-Origin-Opener-Policy "same-origin" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data:; font-src 'self' data: https://fonts.gstatic.com; connect-src 'self'; frame-src https://www.google.com; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'" always;

    # Configurer ici PHP-FPM en transmettant les variables LOGITIX_*.
}
```

## URL admin

Avec la structure actuelle, le point d'entree reste :

```text
https://admin.logitix.ci/admin/connexion.php
```

Ne pas publier `/.git`, les scripts SQL, les documents internes ou les fichiers
de configuration. A terme, la meilleure architecture reste un DocumentRoot
dedie a un repertoire `public/`, lorsque le projet sera restructure sans
modifier les URLs existantes.
