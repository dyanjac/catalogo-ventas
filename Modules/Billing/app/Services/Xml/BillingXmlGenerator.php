<?php

namespace Modules\Billing\Services\Xml;

use Illuminate\Support\Facades\Storage;
use Modules\Billing\Models\BillingDocument;
use SimpleXMLElement;

class BillingXmlGenerator
{
    /**
     * @param array<string,mixed> $payload
     */
    public function generate(BillingDocument $document, array $payload): string
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><ElectronicDocument></ElectronicDocument>');
        $xml->addChild('DocumentType', (string) ($payload['document_type'] ?? $document->document_type));
        $xml->addChild('Series', (string) $document->series);
        $xml->addChild('Number', (string) $document->number);
        $xml->addChild('IssueDate', (string) ($payload['issue_date'] ?? now()->toDateString()));
        $xml->addChild('Currency', (string) ($payload['currency'] ?? $document->currency));

        $customerNode = $xml->addChild('Customer');
        $customer = (array) ($payload['customer'] ?? []);
        $customerNode->addChild('Name', htmlspecialchars((string) ($customer['name'] ?? 'Cliente')));
        $customerNode->addChild('DocumentType', (string) ($customer['document_type'] ?? ''));
        $customerNode->addChild('DocumentNumber', (string) ($customer['document_number'] ?? ''));
        $customerNode->addChild('Address', htmlspecialchars((string) ($customer['address'] ?? '')));

        $totalsNode = $xml->addChild('Totals');
        $totals = (array) ($payload['totals'] ?? []);
        $totalsNode->addChild('Subtotal', number_format((float) ($totals['subtotal'] ?? $document->subtotal), 2, '.', ''));
        $totalsNode->addChild('Discount', number_format((float) ($totals['discount'] ?? 0), 2, '.', ''));
        $totalsNode->addChild('Tax', number_format((float) ($totals['tax'] ?? $document->tax), 2, '.', ''));
        $totalsNode->addChild('Shipping', number_format((float) ($totals['shipping'] ?? 0), 2, '.', ''));
        $totalsNode->addChild('Total', number_format((float) ($totals['total'] ?? $document->total), 2, '.', ''));

        $itemsNode = $xml->addChild('Items');
        foreach ((array) ($payload['items'] ?? []) as $line) {
            $itemNode = $itemsNode->addChild('Item');
            $itemNode->addChild('SKU', htmlspecialchars((string) ($line['sku'] ?? '')));
            $itemNode->addChild('Description', htmlspecialchars((string) ($line['name'] ?? '')));
            $itemNode->addChild('Quantity', number_format((float) ($line['quantity'] ?? 0), 2, '.', ''));
            $itemNode->addChild('UnitPrice', number_format((float) ($line['unit_price'] ?? 0), 2, '.', ''));
            $itemNode->addChild('LineSubtotal', number_format((float) ($line['line_subtotal'] ?? 0), 2, '.', ''));
        }

        $content = $xml->asXML() ?: '';
        $dir = 'billing/xml/'.now()->format('Ym');
        $fileName = implode('-', [$document->document_type, $document->series, $document->number]).'.xml';
        $path = $dir.'/'.$fileName;

        Storage::disk('public')->put($path, $content);

        return $path;
    }
}
