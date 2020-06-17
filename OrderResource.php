<?php

namespace App\Http\Resources\Order;

use App\Http\Controllers\OrderController;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\Order\OrderItemResource;
use App\Http\Resources\Order\OrderStatusResource;
use App\Http\Resources\Order\PaymentTypeResource;
use App\Http\Resources\Order\ShipmentResource;
use App\Http\Resources\StoreResource;
use App\Order;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $invoiceSettings = $this->getInvoiceSettings();
        $deliverNotesSettings = $this->getDeliveryNotesSettings();
        $creditNotesSettings = $this->getCreditNotesSettings();

        $data =  array_merge(parent::toArray($request), [
            'store' => new StoreResource($this->store),
            'shipment' => new ShipmentResource($this->shipment),
            'orderStatus' => new OrderStatusResource($this->orderStatus),
            'customer' => new CustomerResource($this->customer),
            'paymentType' => new PaymentTypeResource($this->paymentType),
            // Order Items
            'items' => OrderItemResource::collection($this->items),
            'documents' => [
                'invoice' => $this->payment()->first()->getCodeWithPrefix($invoiceSettings),
                'dobropis' => $this->payment()->first()->getNumber($creditNotesSettings),
                'dodaci' => $this->payment()->first()->getCodeWithPrefix($deliverNotesSettings)
            ],
            'document_links' => [
                'invoice' => $this->files->where('file_type',OrderController::INVOICE)->first() ? $this->files->where('file_type',OrderController::INVOICE)->first()->getUrlAttribute() : ''
            ],
            'createdDate' => $this->createdDate()
        ]);
        $data["order_identifier"]= $this->getIdentifier();
        return $data;
    }
}
