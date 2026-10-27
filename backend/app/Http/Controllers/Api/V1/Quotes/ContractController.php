<?php

namespace App\Http\Controllers\Api\V1\Quotes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quotes\CloseContractRequest;
use App\Http\Requests\Quotes\ListContractsRequest;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Services\Quotes\ContractService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContractController extends Controller
{
    public function __construct(private readonly ContractService $contracts) {}

    public function index(ListContractsRequest $request): AnonymousResourceCollection
    {
        return ContractResource::collection($this->contracts->list($request->toFilters()));
    }

    public function show(Contract $contract): ContractResource
    {
        return ContractResource::make($this->contracts->show($contract));
    }

    public function close(CloseContractRequest $request, Contract $contract): ContractResource
    {
        return ContractResource::make($this->contracts->close($contract, $request->status()));
    }
}
