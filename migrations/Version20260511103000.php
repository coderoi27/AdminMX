<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260511103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds legal document CMS and editorial gem fields for canonical locations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE legal_documents (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(120) NOT NULL, title VARCHAR(180) NOT NULL, version_label VARCHAR(40) NOT NULL, status VARCHAR(24) NOT NULL, summary LONGTEXT DEFAULT NULL, body LONGTEXT NOT NULL, show_in_footer TINYINT(1) NOT NULL, sort_order INT NOT NULL, effective_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_46D83F5989D9B62 (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE merchant_locations ADD gem_status VARCHAR(24) DEFAULT \'none\' NOT NULL, ADD gem_reason_tags JSON DEFAULT NULL');

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $documents = [
            ['aviso-privacidad', 'Aviso de privacidad', 'Tratamiento de correo, ubicación, interacciones y datos técnicos para operar Mi Monchis MX.', 'Mi Monchis MX trata tu correo, ubicación autorizada, direcciones guardadas, favoritos, interacciones y datos técnicos para operar la webapp, mantenerla segura, enviarte avisos operativos y mejorar la experiencia. No vendemos tus datos. Puedes ejercer derechos ARCO o revocar consentimientos escribiendo a privacidad@mimonchis.mx.'],
            ['cookies', 'Política de cookies', 'Cookies esenciales, preferencias y analítica para el alpha.', 'Usamos cookies esenciales para seguridad y funcionamiento. Las cookies o tecnologías opcionales de analítica ayudan a medir el uso de la plataforma y mejorarla. Puedes aceptar todas, rechazar las no esenciales o ajustar tu decisión desde el banner. Google Maps puede usar tecnologías propias para renderizar mapas y funciones relacionadas.'],
            ['terminos-publico', 'Términos para usuarios', 'Reglas básicas del servicio público de descubrimiento.', 'Mi Monchis MX es una plataforma alpha de descubrimiento de locales. La información puede provenir de dueños, demo interna o terceros como Google Places. El servicio se ofrece en estado alpha y puede cambiar. El usuario debe usar la plataforma sin abuso, scraping, spam o uso indebido.'],
            ['reglas-comunidad', 'Reglas de comunidad', 'Base para contenido, fotos, reseñas y reportes.', 'No se permite contenido ilegal, discriminatorio, difamatorio, violento, sexual explícito, spam, fraude ni contenido que infrinja derechos de terceros. Mi Monchis MX puede moderar, ocultar o retirar contenido y locales cuando exista riesgo o reporte válido.'],
            ['disclaimer-terceros', 'Disclaimer de terceros', 'Datos de Google, mapas, WhatsApp y enlaces externos.', 'Algunos datos, mapas, fotografías, horarios, reseñas o enlaces pueden provenir de terceros. Mi Monchis MX no controla servicios externos como Google Maps, Google Places, WhatsApp o redes sociales. Verifica información crítica directamente con el local.'],
        ];

        foreach ($documents as $index => [$slug, $title, $summary, $body]) {
            $this->addSql(
                'INSERT INTO legal_documents (slug, title, version_label, status, summary, body, show_in_footer, sort_order, effective_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$slug, $title, 'v1.0', 'published', $summary, $body, 1, ($index + 1) * 10, $now, $now, $now]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE legal_documents');
        $this->addSql('ALTER TABLE merchant_locations DROP gem_status, DROP gem_reason_tags');
    }
}
