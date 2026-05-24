<?php

declare(strict_types=1);

namespace App\Api\Controller\V1;

use App\Api\Dto\V1\ListTransfersRequest;
use App\Api\Response\V1\TransferResourceMapper;
use App\Application\Transfer\ListTransfersHandler;
use App\Application\Transfer\ListTransfersQuery;
use App\Domain\Account\AccountRepositoryInterface;
use App\Application\Port\AccountBalanceCacheInterface;
use App\Domain\Exception\TransferException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/v1/accounts')]
final class AccountController extends AbstractController
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly AccountBalanceCacheInterface $balanceCache,
        private readonly ListTransfersHandler $listHandler,
        private readonly TransferResourceMapper $mapper,
    ) {
    }

    #[Route('/{id}', name: 'api_v1_account_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            return $this->json(['error' => 'invalid_id', 'message' => 'Invalid account UUID.'], Response::HTTP_BAD_REQUEST);
        }

        $cached = $this->balanceCache->get($id);
        $account = $this->accountRepository->findById($id);

        if ($account === null) {
            throw TransferException::accountNotFound($id);
        }

        if ($cached === null) {
            $this->balanceCache->set($id, $account->balance());
        }

        return $this->json([
            'id' => $account->id,
            'accountNumber' => $account->accountNumber,
            'balance' => $account->balance()->toMajorString(),
            'currency' => $account->balance()->currency,
            'active' => $account->active,
            'cached' => $cached !== null,
        ]);
    }

    #[Route('/{id}/transfers', name: 'api_v1_account_transfers', methods: ['GET'])]
    public function transfers(string $id, #[MapQueryString] ListTransfersRequest $query): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            return $this->json(['error' => 'invalid_id', 'message' => 'Invalid account UUID.'], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->listHandler->handle(new ListTransfersQuery(
            accountId: $id,
            status: $query->status,
            days: $query->days,
            page: $query->page,
            limit: $query->limit,
        ));

        return $this->json($this->mapper->fromPaginatedResult($result));
    }
}
