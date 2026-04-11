<?php

namespace Uepg\LaravelSybase\Database;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;

class RpcCall
{
    protected array $parameters = [];

    protected bool $useReadPdo = true;

    protected array $fetchUsing = [];

    protected ?array $rows = null;

    public function __construct(
        protected Connection $connection,
        protected string $procedure,
    ) {
        $this->procedure = $this->assertValidProcedureName($procedure);
    }

    /**
     * @param  array<string, mixed>|object  $parameters
     */
    public function with(array|object $parameters): self
    {
        $this->rows = null;
        $this->parameters = array_merge($this->parameters, $this->normalizeParameters($parameters));

        return $this;
    }

    public function useReadPdo(bool $useReadPdo = true): self
    {
        $this->rows = null;
        $this->useReadPdo = $useReadPdo;

        return $this;
    }

    /**
     * @param  array<int, mixed>  $fetchUsing
     */
    public function fetchUsing(array $fetchUsing): self
    {
        $this->rows = null;
        $this->fetchUsing = $fetchUsing;

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get(): array
    {
        return $this->execute();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        $rows = $this->execute();

        return $rows[0] ?? null;
    }

    public function assertOk(): self
    {
        $rows = $this->execute();
        $first = $rows[0] ?? null;

        if ($first === null) {
            throw new InvalidArgumentException(
                'Procedure returned no rows; assertOk() requires a row with cd_retorno.'
            );
        }

        $cd = $this->findColumnValue($first, 'cd_retorno');

        if ($cd === null) {
            throw new InvalidArgumentException(
                'Procedure result is missing cd_retorno; cannot assertOk().'
            );
        }

        if ((int) $cd !== 0) {
            $msg = $this->findColumnValue($first, 'msg_retorno');

            throw new ProcedureExecutionException(
                (int) $cd,
                $msg !== null ? (string) $msg : null,
            );
        }

        return $this;
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    public function toStatement(): array
    {
        return $this->compileExecute();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function execute(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        [$query, $bindings] = $this->compileExecute();

        $this->rows = $this->connection->select(
            $query,
            $bindings,
            $this->useReadPdo,
            $this->fetchUsing,
        );

        return $this->rows;
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function compileExecute(): array
    {
        $bindings = [];
        $fragments = [];

        foreach ($this->parameters as $name => $value) {
            $fragments[] = '@'.$name.' = ?';
            $bindings[] = $value;
        }

        $sql = $fragments === []
            ? 'EXEC '.$this->procedure
            : 'EXEC '.$this->procedure.' '.implode(', ', $fragments);

        return [$sql, $bindings];
    }

    /**
     * @param  array<string, mixed>|object  $parameters
     * @return array<string, mixed>
     */
    protected function normalizeParameters(array|object $parameters): array
    {
        $data = $this->parametersToArray($parameters);
        $normalized = [];

        foreach ($data as $key => $value) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('Named procedure parameters require string keys.');
            }

            $canonical = $this->normalizeParameterKey($key);
            $normalized[$canonical] = $value;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>|object  $parameters
     * @return array<string, mixed>
     */
    protected function parametersToArray(array|object $parameters): array
    {
        if ($parameters instanceof Arrayable) {
            return $parameters->toArray();
        }

        if (is_object($parameters)) {
            return get_object_vars($parameters) ?: (array) $parameters;
        }

        return $parameters;
    }

    protected function normalizeParameterKey(string $key): string
    {
        $key = trim($key);

        if ($key === '') {
            throw new InvalidArgumentException('Parameter name cannot be empty.');
        }

        if (str_starts_with($key, '@')) {
            $key = substr($key, 1);
        }

        if ($key === '' || ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
            throw new InvalidArgumentException(sprintf('Invalid parameter name "%s".', $key));
        }

        return strtolower($key);
    }

    protected function assertValidProcedureName(string $procedure): string
    {
        $procedure = trim($procedure);

        if ($procedure === '' || ! preg_match('/^[a-zA-Z0-9_#.\[\]]+$/', $procedure)) {
            throw new InvalidArgumentException('Invalid stored procedure name.');
        }

        return $procedure;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function findColumnValue(array $row, string $name): mixed
    {
        foreach ($row as $column => $value) {
            if (strcasecmp((string) $column, $name) === 0) {
                return $value;
            }
        }

        return null;
    }
}
