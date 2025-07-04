<?php

namespace App\Events;

use App\DataTransferObjects\QuickRequestData;
use App\Models\Order\ProductRequest;
use App\Models\PatientManufacturerIVREpisode;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuickRequestSubmitted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PatientManufacturerIVREpisode $episode,
        public ProductRequest $productRequest,
        public QuickRequestData $requestData,
        public array $calculationData,
    ) {}
} 