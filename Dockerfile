FROM php:8.3-apache

COPY docker/apache-security.conf /etc/apache2/conf-available/tvsumare-security.conf

RUN a2enmod rewrite headers \
    && a2enconf tvsumare-security \
    && sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

COPY . /var/www/html/

RUN php /var/www/html/docker/apply-radar-editorial-policy.php \
    && php /var/www/html/docker/apply-video-ai-hardening.php \
    && php /var/www/html/docker/apply-boletim-diversity-hardening.php \
    && php /var/www/html/docker/apply-social-distribution-hardening.php \
    && php /var/www/html/docker/apply-admin-module-hardening.php \
    && php /var/www/html/docker/apply-reporter-queue-hardening.php \
    && php /var/www/html/docker/apply-heygen-recovery.php \
    && php /var/www/html/docker/run-homologation-smoke.php \
    && rm -f /var/www/html/docker/apply-radar-editorial-policy.php \
    && rm -f /var/www/html/docker/apply-video-ai-hardening.php \
    && rm -f /var/www/html/docker/apply-boletim-diversity-hardening.php \
    && rm -f /var/www/html/docker/apply-social-distribution-hardening.php \
    && rm -f /var/www/html/docker/apply-admin-module-hardening.php \
    && rm -f /var/www/html/docker/apply-reporter-queue-hardening.php \
    && rm -f /var/www/html/docker/apply-heygen-recovery.php \
    && rm -f /var/www/html/docker/run-homologation-smoke.php \
    && chown -R root:root /var/www/html \
    && find /var/www/html -type d -exec chmod 0755 {} + \
    && find /var/www/html -type f -exec chmod 0644 {} +

EXPOSE 80
