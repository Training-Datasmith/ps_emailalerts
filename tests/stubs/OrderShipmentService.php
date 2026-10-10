<?php

namespace PrestaShop\PrestaShop\Adapter\Shipment;

class OrderShipmentService
{
    public function orderHasShipment($orderId)
    {
        return true;
    }

    public function getCarrierForProduct($orderId, $productId)
    {
        $c = new \Carrier();
        $c->name = 'ParcelCo';

        return $c;
    }

    public function getAllCarriersForOrder($orderId)
    {
        $c = new \Carrier();
        $c->name = 'ParcelCo';

        return [$c];
    }
}
