<?php

declare(strict_types=1);

namespace App\UI\Home;

use App\Services\StackSwapService;
use Nette;


final class HomePresenter extends Nette\Application\UI\Presenter
{
    public function __construct(public string $apiKey, public StackSwapService $stackSwapService)
    {
        parent::__construct();
    }

    public function actionCollect(string $key): void
    {
        if ($key !== $this->apiKey) {
            return $this->sendJson(['status' => 'error', 'message' => 'Invalid key']);
        }

        $this->stackSwapService->collect();
        $this->sendJson(['status' => 'ok']);
    }

    public function renderDefault(): void
    {
        $this->template->pools = $this->stackSwapService->getNewPools();
        $this->template->poolsWithLiquidity = $this->stackSwapService->getNewPoolsWithLiquidity();
    }
}
