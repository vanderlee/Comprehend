<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use LogicException;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Failure;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

trait SingleUnitHooks
{
    /** @var list<string> */
    public array $hooks = [];
    public ?ParseSession $session = null;
    public ?bool $caseInHook = null;
    public bool $throwInHook = false;

    protected function success(Source $source, int $offset, int $length = 0, array|Success $children = []): Success
    {
        $this->observeHook('success');

        return parent::success($source, $offset, $length, $children);
    }

    protected function failure(Source $source, int $offset, int $progress = 0): Failure
    {
        $this->observeHook('failure');

        return parent::failure($source, $offset, $progress);
    }

    protected function makeMatch(
        bool $accepted,
        Source $source,
        int $offset,
        int $length = 0,
        array|Success $children = [],
    ): AbstractMatch {
        $this->hooks[] = 'makeMatch';

        return parent::makeMatch($accepted, $source, $offset, $length, $children);
    }

    private function observeHook(string $hook): void
    {
        $this->hooks[] = $hook;
        $this->caseInHook = $this->session->isCaseSensitive();
        if ($this->throwInHook) {
            throw new LogicException('unit hook failed');
        }
    }
}
