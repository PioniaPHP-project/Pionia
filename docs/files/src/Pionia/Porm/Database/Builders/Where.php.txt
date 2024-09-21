<?php

/**
 * PORM - CDatabase querying tool for pionia framework.
 *
 * This package can be used as is or with the Pionia Framework. Anyone can reproduce and update this as they see fit.
 *
 * @copyright 2024,  Pionia Project - Jet Ezra
 *
 * @author Jet Ezra
 * @version 1.0.0
 * @link https://pionia.netlify.app/
 * @license https://opensource.org/licenses/MIT
 *
 **/

namespace Pionia\Porm\Database\Builders;

use Porm\Core\ContractBuilder;
use Ramsey\Uuid\Nonstandard\Uuid;

class Where extends ContractBuilder
{
    private array $where = [];

    public function build(): array
    {
        return $this->where;
    }

    public function or(array $clauses): static
    {
        $this->where = array_merge($this->where, [$this->commented('OR') => $clauses]);
        return $this;
    }

    public function and(array $clauses): static
    {
        $this->where = array_merge($this->where, [$this->commented('AND') => $clauses]);
        return $this;
    }

    private function commented($hook): string
    {
        return $hook . ' #' . Uuid::uuid4()->toString();
    }

    /**
     * Add and clauses
     *
     * @example ```php
     *  Where::builder()->where(['name' => 'Pionia', 'type' => 'Framework'])->builder ---- WHERE name = 'Pionia' AND type = 'Framework'
     * ```
     * @param array $where
     * @return $this
     */
    public function where(array $where): static
    {
        $this->where = array_merge($this->where, $where);
        return $this;
    }

}
