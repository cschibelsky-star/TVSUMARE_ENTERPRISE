FROM php:8.3-apache

COPY docker/apache-security.conf /etc/apache2/conf-available/tvsumare-security.conf
COPY docker/tvsumare-entrypoint.sh /usr/local/bin/tvsumare-entrypoint

RUN apt-get update \
    && apt-get install -y --no-install-recommends ffmpeg curl \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers \
    && a2enconf tvsumare-security \
    && chmod 0755 /usr/local/bin/tvsumare-entrypoint \
    && sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
    && printf '%s\n' 'upload_max_filesize=512M' 'post_max_size=520M' 'upload_tmp_dir=/var/www/html/uploads/.tmp' 'max_execution_time=300' 'max_input_time=300' > /usr/local/etc/php/conf.d/tvsumare-uploads.ini

COPY . /var/www/html/

RUN set -eux; \
    base='https://raw.githubusercontent.com/cschibelsky-star/TVSUMARE_ENTERPRISE/4c29edad2eba999a3c1f446549c2793d087c5b9b/assets'; \
    curl -fsSL "$base/thumb-educacao.jpg" -o /var/www/html/assets/thumb-educacao.jpg; \
    curl -fsSL "$base/thumb-esportes.jpg" -o /var/www/html/assets/thumb-esportes.jpg; \
    curl -fsSL "$base/thumb-infraestrutura.jpg" -o /var/www/html/assets/thumb-infraestrutura.jpg; \
    curl -fsSL "$base/thumb-politica.jpg" -o /var/www/html/assets/thumb-politica.jpg; \
    curl -fsSL "$base/thumb-saude.jpg" -o /var/www/html/assets/thumb-saude.jpg; \
    curl -fsSL "$base/thumb-seguranca.jpg" -o /var/www/html/assets/thumb-seguranca.jpg

RUN php /var/www/html/docker/apply-radar-editorial-policy.php \
    && php /var/www/html/docker/apply-radar-image-audit.php \
    && php /var/www/html/docker/apply-video-ai-hardening.php \
    && php /var/www/html/docker/apply-boletim-diversity-hardening.php \
    && php /var/www/html/docker/apply-social-distribution-hardening.php \
    && php /var/www/html/docker/apply-admin-module-hardening.php \
    && php /var/www/html/docker/apply-reporter-queue-hardening.php \
    && php /var/www/html/docker/apply-heygen-recovery.php \
    && php /var/www/html/docker/apply-boletim-auto-presenter-ux.php \
    && php /var/www/html/docker/apply-reporter-humanization.php \
    && php /var/www/html/docker/apply-presenter-catalog-integration.php \
    && php /var/www/html/docker/apply-heygen-credit-revalidation.php \
    && php /var/www/html/docker/apply-reporter-dedup-hardening.php \
    && php /var/www/html/docker/apply-heygen-v3-schema-fix.php \
    && php /var/www/html/docker/apply-video-branding.php \
    && php /var/www/html/docker/apply-youtube-publishing-integration.php \
    && php /var/www/html/docker/apply-tvsumare-recovery-20260911.php \
    && php /var/www/html/docker/apply-editorial-policy-v3.php \
    && php /var/www/html/tests/editorial_policy_v3_test.php \
    && php /var/www/html/docker/test-reporter-dedup.php \
    && php /var/www/html/docker/test-youtube-integration.php \
    && php /var/www/html/docker/run-homologation-smoke.php \
    && rm -f /var/www/html/docker/apply-radar-editorial-policy.php \
    && rm -f /var/www/html/docker/apply-radar-image-audit.php \
    && rm -f /var/www/html/docker/apply-video-ai-hardening.php \
    && rm -f /var/www/html/docker/apply-boletim-diversity-hardening.php \
    && rm -f /var/www/html/docker/apply-social-distribution-hardening.php \
    && rm -f /var/www/html/docker/apply-admin-module-hardening.php \
    && rm -f /var/www/html/docker/apply-reporter-queue-hardening.php \
    && rm -f /var/www/html/docker/apply-heygen-recovery.php \
    && rm -f /var/www/html/docker/apply-boletim-auto-presenter-ux.php \
    && rm -f /var/www/html/docker/apply-reporter-humanization.php \
    && rm -f /var/www/html/docker/apply-presenter-catalog-integration.php \
    && rm -f /var/www/html/docker/apply-heygen-credit-revalidation.php \
    && rm -f /var/www/html/docker/apply-reporter-dedup-hardening.php \
    && rm -f /var/www/html/docker/apply-heygen-v3-schema-fix.php \
    && rm -f /var/www/html/docker/apply-video-branding.php \
    && rm -f /var/www/html/docker/apply-youtube-publishing-integration.php \
    && rm -f /var/www/html/docker/apply-tvsumare-recovery-20260911.php \
    && rm -f /var/www/html/docker/apply-editorial-policy-v3.php \
    && rm -f /var/www/html/docker/test-reporter-dedup.php \
    && rm -f /var/www/html/docker/test-youtube-integration.php \
    && rm -f /var/www/html/docker/run-homologation-smoke.php \
    && rm -f /var/www/html/api/veo-e2e-*.php /var/www/html/veo-e2e-*.php \
    && chown -R root:root /var/www/html \
    && find /var/www/html -type d -exec chmod 0755 {} + \
    && find /var/www/html -type f -exec chmod 0644 {} +

ENTRYPOINT ["tvsumare-entrypoint"]
CMD ["apache2-foreground"]
EXPOSE 80
