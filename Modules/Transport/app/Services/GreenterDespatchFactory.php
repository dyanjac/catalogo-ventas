<?php

declare(strict_types=1);

namespace Modules\Transport\Services;

use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Despatch\AdditionalDoc;
use Greenter\Model\Despatch\Despatch;
use Greenter\Model\Despatch\DespatchDetail;
use Greenter\Model\Despatch\Direction;
use Greenter\Model\Despatch\Driver;
use Greenter\Model\Despatch\Shipment;
use Greenter\Model\Despatch\Transportist;
use Greenter\Model\Despatch\Vehicle;
use Modules\Transport\Enums\TransportGuideType;
use Modules\Transport\Models\TransportGuide;
use Modules\Transport\Models\TransportSetting;

class GreenterDespatchFactory
{
    public function build(TransportSetting $setting, TransportGuide $guide): Despatch
    {
        $guide->loadMissing(['items', 'relatedGuide', 'billingDocument']);
        $credentials = $setting->provider_credentials ?? [];
        $shipment = (new Shipment)
            ->setModTraslado($guide->transport_mode->value)
            ->setCodTraslado((string) $guide->reason_code)
            ->setDesTraslado((string) config('transport.reasons.'.$guide->reason_code, 'OTROS'))
            ->setFecTraslado(new \DateTime($guide->transfer_at->format('Y-m-d H:i:s')))
            ->setPesoTotal((float) $guide->gross_weight)
            ->setUndPesoTotal((string) $guide->weight_unit)
            ->setNumBultos($guide->package_count)
            ->setPartida($this->direction($guide->origin_snapshot))
            ->setLlegada($this->direction($guide->destination_snapshot));

        $transport = $guide->transport_snapshot;
        if ($guide->transport_mode->value === '01') {
            $shipment->setTransportista((new Transportist)
                ->setTipoDoc((string) ($transport['carrier_document_type'] ?? '6'))
                ->setNumDoc((string) ($transport['carrier_document_number'] ?? ''))
                ->setRznSocial((string) ($transport['carrier_name'] ?? ''))
                ->setNroMtc((string) ($transport['mtc_registration'] ?? '')));
        } else {
            $shipment->setVehiculo((new Vehicle)->setPlaca((string) ($transport['vehicle_plate'] ?? '')));
            $shipment->setChoferes([(new Driver)
                ->setTipo('Principal')
                ->setTipoDoc((string) ($transport['driver_document_type'] ?? '1'))
                ->setNroDoc((string) ($transport['driver_document_number'] ?? ''))
                ->setNombres((string) ($transport['driver_name'] ?? ''))
                ->setApellidos((string) ($transport['driver_last_name'] ?? '-'))
                ->setLicencia((string) ($transport['driver_license'] ?? ''))]);
        }

        $document = (new Despatch)
            ->setVersion('2022')
            ->setTipoDoc($guide->guide_type->sunatCode())
            ->setSerie((string) $guide->series)
            ->setCorrelativo((string) $guide->number)
            ->setFechaEmision(new \DateTime($guide->issue_date->format('Y-m-d')))
            ->setCompany($this->company($credentials))
            ->setDestinatario($this->client($guide->recipient_snapshot))
            ->setObservacion((string) ($guide->notes ?? ''))
            ->setEnvio($shipment)
            ->setDetails($guide->items->map(fn ($item) => (new DespatchDetail)
                ->setCantidad((float) $item->quantity)
                ->setUnidad((string) $item->unit_code)
                ->setDescripcion((string) $item->description)
                ->setCodigo((string) $item->code)
                ->setCodProdSunat($item->sunat_product_code))->all());

        $related = [];
        if ($guide->guide_type === TransportGuideType::Carrier && $guide->relatedGuide) {
            $related[] = (new AdditionalDoc)
                ->setTipoDesc('Guia de Remision Remitente')
                ->setTipo('09')
                ->setNro($guide->relatedGuide->formattedNumber())
                ->setEmisor((string) ($guide->relatedGuide->origin_snapshot['ruc'] ?? $credentials['company_ruc'] ?? ''));
        } elseif ($guide->guide_type === TransportGuideType::Carrier && $guide->external_sender_snapshot) {
            $related[] = (new AdditionalDoc)
                ->setTipoDesc('Guia de Remision Remitente')
                ->setTipo((string) $guide->external_sender_snapshot['document_type'])
                ->setNro((string) $guide->external_sender_snapshot['number'])
                ->setEmisor((string) $guide->external_sender_snapshot['issuer_ruc']);
        }
        if ($guide->billingDocument) {
            $related[] = (new AdditionalDoc)
                ->setTipoDesc($guide->billingDocument->document_type === 'factura' ? 'Factura' : 'Boleta')
                ->setTipo($guide->billingDocument->document_type === 'factura' ? '01' : '03')
                ->setNro($guide->billingDocument->series.'-'.$guide->billingDocument->number)
                ->setEmisor((string) ($credentials['company_ruc'] ?? ''));
        }
        if ($related !== []) {
            $document->setAddDocs($related);
        }

        return $document;
    }

    /** @param array<string, mixed> $data */
    private function direction(array $data): Direction
    {
        return (new Direction((string) ($data['ubigeo'] ?? ''), (string) ($data['address'] ?? '')))
            ->setCodLocal((string) ($data['establishment_code'] ?? '0000'))
            ->setRuc(isset($data['ruc']) ? (string) $data['ruc'] : null);
    }

    /** @param array<string, mixed> $data */
    private function client(array $data): Client
    {
        return (new Client)
            ->setTipoDoc((string) ($data['document_type'] ?? '6'))
            ->setNumDoc((string) ($data['document_number'] ?? ''))
            ->setRznSocial((string) ($data['name'] ?? ''));
    }

    /** @param array<string, mixed> $data */
    private function company(array $data): Company
    {
        return (new Company)
            ->setRuc((string) ($data['company_ruc'] ?? ''))
            ->setRazonSocial((string) ($data['company_legal_name'] ?? ''))
            ->setNombreComercial((string) ($data['company_commercial_name'] ?? $data['company_legal_name'] ?? ''))
            ->setAddress((new Address)
                ->setUbigueo((string) ($data['company_ubigeo'] ?? ''))
                ->setDepartamento((string) ($data['company_department'] ?? ''))
                ->setProvincia((string) ($data['company_province'] ?? ''))
                ->setDistrito((string) ($data['company_district'] ?? ''))
                ->setDireccion((string) ($data['company_address'] ?? '')));
    }
}
