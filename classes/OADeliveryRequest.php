<?php

class OADeliveryRequest
{
    public $id;
    public $extId;
    public $orderId;
    public $rateId;
    public $senderId;
    public $trackingNumber;
    public $recipientName;
    public $recipientPhone;
    public $recipientComment;
    public $deliveryService;
    public $deliveryCost;
    public $comment;

    public $localityId;
    public $postcode;
    public $street;
    public $house;
    public $apartment;
    public $notFormal;
    public $servicePoint;

    public function __construct()
    {

        $this->deliveryService = 1;
        $this->retailPrice = 0;
        $this->error   = '';
    }

    public function updatefromArray($data)
    {
        $this->localityId   = $data['locality']     ?? $this->localityId;
        $this->postcode     = $data['postcode']     ?? $this->postcode;
        $this->street       = $data['street']       ?? $this->street;
        $this->house        = $data['house']        ?? $this->house;
        $this->apartment    = $data['apartment']    ?? $this->apartment;
        $this->notFormal    = $data['notFormal']    ?? $this->notFormal ;
        $this->retailPrice  = $data['retailPrice']  ?? $this->retailPrice;
        $this->servicePoint = $data['servicePoint'] ?? $this->servicePoint;
    }

    public function updatefromServicePoint($oa_spoint)
    {
        $this->localityId   = $oa_spoint->get('_embedded/locality/id');
        $this->postcode     = $oa_spoint->get('_embedded/locality/postcode');
        $this->street       = '';
        $this->house        = '';
        $this->apartment    = '';
        $this->notFormal    = $oa_spoint->get('rawAddress');
        $this->servicePoint = $oa_spoint->get('id');
    }

    protected function getReqMainBlock()
    {
        $data = [
            'rate'   => $this->rateId,
            'sender' => $this->senderId,
            'deliveryService'   => $this->deliveryService,
            'recipientLocality' => $this->localityId
        ];
        if(!empty($this->extId)) {
            $data['extId'] = $this->extId;
        }
        if(!empty($this->deliveryCost)) {
            $data['retailPrice'] = $this->deliveryCost;
        }
        if(!empty($this->trackingNumber)) {
            $data['trackingNumber'] = $this->trackingNumber;
        }
        if($this->servicePoint) {
            $data['servicePoint'] = $this->servicePoint;
        }
        if($this->recipientComment) {
            $data['recipientComment'] = $this->recipientComment;
        }
        if($this->recipientPhone) {
            $data['recipientPhone'] = $this->recipientPhone;
        }
        if($this->recipientName) {
            $data['recipientName'] = $this->recipientName;
        }
        if($this->comment) {
            $data['recipientComment'] = $this->comment;
        }
        return $data;
    }

    protected function getReqAddressBlock()
    {
        $data = [];
        $data['postcode']  = $this->postcode;
        $data['notFormal'] = $this->notFormal;
        if($this->servicePoint) {
            $data['street']    = '';
            $data['house']     = '';
            $data['apartment'] = ''; 
        } else {
            $data['street']    = $this->street;
            $data['house']     = $this->house;
            $data['apartment'] = mb_substr(strval($this->apartment), 0, 6);
        }
        return $data;
    }

    public function toOrderRequest()
    {
        $main_block = $this->getReqMainBlock();
        $main_block['recipientAddress'] = $this->getReqAddressBlock();
        return [
            'deliveryRequest' => $main_block
        ];
    }

    public function toStandaloneRequest()
    {
        $main_block = $this->getReqMainBlock();
        $main_block['order'] = $this->orderId;
        $main_block['recipientAddress'] = $this->getReqAddressBlock();
        return $main_block;
    }


}