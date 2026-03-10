<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('document_templates')) {
            Schema::create('document_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('name', 120);
                $table->string('document_type', 30);
                $table->longText('xslt_content');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['document_type', 'is_active']);
                $table->index(['company_id', 'document_type', 'is_active'], 'document_templates_company_type_active_idx');
            });
        }

        $defaultXslt = <<<'XSL'
<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
    xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2">
    <xsl:output method="html" encoding="UTF-8" indent="yes"/>
    <xsl:template match="/">
        <html>
        <head>
            <meta charset="utf-8" />
            <style>
                body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #222; }
                .wrap { width: 100%; }
                .header { margin-bottom: 18px; border-bottom: 1px solid #ddd; padding-bottom: 8px; }
                .title { font-size: 18px; font-weight: bold; margin: 0; }
                .muted { color: #666; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                th, td { border: 1px solid #ddd; padding: 6px; }
                th { background: #f4f6f9; text-align: left; }
                .right { text-align: right; }
                .totals { margin-top: 12px; width: 40%; margin-left: auto; }
            </style>
        </head>
        <body>
            <div class="wrap">
                <div class="header">
                    <p class="title">
                        <xsl:value-of select="/*/cbc:ID"/>
                    </p>
                    <p class="muted">Fecha emisión: <xsl:value-of select="/*/cbc:IssueDate"/></p>
                    <p>Emisor: <xsl:value-of select="/*/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName"/></p>
                    <p>Cliente: <xsl:value-of select="/*/cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName"/></p>
                </div>
                <table>
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Descripción</th>
                        <th class="right">Cantidad</th>
                        <th class="right">P. Unitario</th>
                        <th class="right">Importe</th>
                    </tr>
                    </thead>
                    <tbody>
                    <xsl:for-each select="/*/cac:InvoiceLine">
                        <tr>
                            <td><xsl:value-of select="position()"/></td>
                            <td><xsl:value-of select="cac:Item/cbc:Description"/></td>
                            <td class="right"><xsl:value-of select="cbc:InvoicedQuantity"/></td>
                            <td class="right"><xsl:value-of select="cac:Price/cbc:PriceAmount"/></td>
                            <td class="right"><xsl:value-of select="cbc:LineExtensionAmount"/></td>
                        </tr>
                    </xsl:for-each>
                    </tbody>
                </table>
                <table class="totals">
                    <tr>
                        <th>Total</th>
                        <td class="right"><xsl:value-of select="/*/cac:LegalMonetaryTotal/cbc:PayableAmount"/></td>
                    </tr>
                </table>
            </div>
        </body>
        </html>
    </xsl:template>
</xsl:stylesheet>
XSL;

        $types = ['factura', 'boleta', 'nota_credito', 'nota_debito', 'retencion', 'recibo_honorarios'];
        $now = now();
        foreach ($types as $type) {
            DB::table('document_templates')->updateOrInsert(
                ['company_id' => null, 'document_type' => $type, 'name' => 'Default '.$type],
                [
                    'xslt_content' => $defaultXslt,
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};

