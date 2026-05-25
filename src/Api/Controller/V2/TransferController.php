<?php

declare(strict_types=1);

namespace App\Api\Controller\V2;

use App\Api\Dto\V1\ListTransfersRequest;
use App\Api\Dto\V2\CreateTransferRequest;
use App\Api\Response\V2\ApiEnvelope;
use App\Api\Response\V2\TransferResourceMapper;
use App\Application\Transfer\ListTransfersHandler;
use App\Application\Transfer\ListTransfersQuery;
use App\Application\Transfer\TransferFundsCommand;
use App\Application\Transfer\TransferFundsHandler;
use App\Domain\Transfer\TransferRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v2/transfers')]
final class TransferController extends AbstractController
{
    public function __construct(
        private readonly TransferFundsHandler $handler,
        private readonly ListTransfersHandler $listHandler,
        private readonly TransferRepositoryInterface $transferRepository,
        private readonly TransferResourceMapper $mapper,
    ) {
    }

    #[Route('', name: 'api_v2_transfer_create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateTransferRequest $payload,
        Request $request,
    ): JsonResponse {
        $idempotencyKey = $request->headers->get('Idempotency-Key');
        assert($idempotencyKey !== null);

        $result = $this->handler->handle(new TransferFundsCommand(
            $payload->fromAccountId,
            $payload->toAccountId,
            $payload->amount,
            strtoupper((string) $payload->currency),
            $idempotencyKey,
        ));

        $statusCode = $result->wasReplayed ? Response::HTTP_OK : Response::HTTP_CREATED;

        return $this->json(
            ApiEnvelope::single($this->mapper->fromCreateResult($result, $payload->description)),
            $statusCode,
        );
    }

    #[Route('', name: 'api_v2_transfer_list', methods: ['GET'])]
    public function list(#[MapQueryString] ListTransfersRequest $query): JsonResponse
    {
        $result = $this->listHandler->handle(new ListTransfersQuery(
            accountId: $query->accountId,
            status: $query->status,
            days: $query->days,
            page: $query->page,
            limit: $query->limit,
        ));

        return $this->json($this->mapper->fromPaginatedResult($result));
    }

    #[Route('/recent', name: 'api_v2_transfer_recent', methods: ['GET'])]
    public function recent(#[MapQueryString] ListTransfersRequest $query): JsonResponse
    {
        $days = $query->days ?? 30;

        $result = $this->listHandler->handle(new ListTransfersQuery(
            accountId: $query->accountId,
            status: $query->status,
            days: $days,
            page: $query->page,
            limit: $query->limit,
        ));

        return $this->json(ApiEnvelope::paginated(
            array_map(
                fn ($transfer) => $this->mapper->fromTransfer($transfer),
                $result->items,
            ),
            $result,
            ['periodDays' => $days],
        ));
    }

    #[Route('/{reference}', name: 'api_v2_transfer_show', methods: ['GET'])]
    public function show(string $reference): JsonResponse
    {
        $transfer = $this->transferRepository->findByReference($reference);

        if ($transfer === null) {
            return $this->json(ApiEnvelope::single([
                'error' => 'TRANSFER_NOT_FOUND',
                'message' => sprintf('Transfer not found: %s', $reference),
            ]), Response::HTTP_NOT_FOUND);
        }

        return $this->json(ApiEnvelope::single($this->mapper->fromTransfer($transfer)));
    }
}
