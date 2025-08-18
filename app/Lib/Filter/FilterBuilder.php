<?php

namespace App\Lib\Filter;

use BackedEnum;
use Carbon\Carbon;
use Carbon\CarbonTimeZone;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\InputBag;

class FilterBuilder
{
    protected array $sortable = [];
    protected array $sortBy = [];
    protected array $filters = [];

    protected ?InputBag $input = null;

    private ?CarbonTimeZone $timezone = null;

    public function sortable(string|array $name, string|Expression|null $field = null): static
    {
        if (is_array($name)) {
            foreach ($name as $n => $field) {
                if (is_int($n)) {
                    $this->sortable[$field] = $field;
                } else {
                    $this->sortable[$n] = $field;
                }
            }
            return $this;
        }
        if (is_null($field)) {
            $field = $name;
        }
        $this->sortable[$name] = $field;
        return $this;
    }

    public function sortBy(string $name, string $dir = 'asc'): static
    {
        if (!isset($this->sortable[$name])) {
            throw new InvalidArgumentException("Sortable field '$name' is not defined.");
        }
        $this->sortBy = [$name, $dir];
        return $this;
    }

    /**
     * @param string $name
     * @param callable(Builder $builder, string $value, string $name): mixed $callback
     * @param string|null $default
     * @return $this
     */
    public function withString(string $name, callable $callback, ?string $default = null): static
    {
        $this->filters[$name] = [
            'type' => 'string',
            'callback' => $callback,
            'default' => $default,
        ];
        return $this;
    }

    /**
     * @param string $name
     * @param callable(Builder $builder, array<int, string> $values, string $name): mixed $callback
     * @param array|null $default
     * @return $this
     */
    public function withStrings(string $name, callable $callback, ?array $default = null): static
    {
        $this->filters[$name] = [
            'type' => 'strings',
            'callback' => $callback,
            'default' => $default,
        ];
        return $this;
    }

    /**
     * @param string $name
     * @param callable(Builder $builder, int $value, string $name): mixed $callback
     * @param int|null $default
     * @return $this
     */
    public function withInt(string $name, callable $callback, ?int $default = null): static
    {
        $this->filters[$name] = [
            'type' => 'int',
            'callback' => $callback,
            'default' => $default,
        ];
        return $this;
    }

    /**
     * @param string $name
     * @param callable(Builder $builder, array<int, int> $values, string $name): mixed $callback
     * @param array|null $default
     * @return $this
     */
    public function withInts(string $name, callable $callback, ?array $default = null): static
    {
        $this->filters[$name] = [
            'type' => 'ints',
            'callback' => $callback,
            'default' => $default,
        ];
        return $this;
    }

    /**
     * @param string $name
     * @param callable(Builder $builder, BackedEnum $enum, string $name): mixed $callback
     * @param string $enum
     * @param BackedEnum|null $default
     * @return $this
     */
    public function withEnum(string $name, callable $callback, string $enum, ?BackedEnum $default = null): static
    {
        if (!is_subclass_of($enum, BackedEnum::class)) {
            throw new InvalidArgumentException("\$enum must be a BackedEnum.");
        }
        $this->filters[$name] = [
            'type' => 'enum',
            'enum' => $enum,
            'callback' => $callback,
            'default' => $default,
        ];
        return $this;
    }

    /**
     * @param string $name
     * @param callable(Builder $builder, array<int, BackedEnum> $enums, string $name): mixed $callback
     * @param string $enum
     * @param array<int, BackedEnum>|null $default
     * @return $this
     */
    public function withEnums(string $name, callable $callback, string $enum, ?array $default = null): static
    {
        if (!is_subclass_of($enum, BackedEnum::class)) {
            throw new InvalidArgumentException("\$enum must be a BackedEnum.");
        }
        $this->filters[$name] = [
            'type' => 'enums',
            'enum' => $enum,
            'callback' => $callback,
            'default' => $default,
        ];
        return $this;
    }

    public function withNumeric(string $name, string|Expression $field): static
    {
        $this->filters[$name] = [
            'type' => 'numeric',
            'field' => $field,
        ];
        return $this;
    }

    public function withDates(string $name, string|Expression $field): static
    {
        $this->filters[$name] = [
            'type' => 'date',
            'field' => $field,
        ];
        return $this;
    }

    public function apply(string|Builder $src, Request $request, ?array &$meta = []): LengthAwarePaginator
    {
        $this->pageless($src, $request, $meta);

        $limit = 15;
        if ($this->input->has('limit')) {
            $limit = $this->input->getInt('limit');
            if ($limit < 1 || $limit > 100) {
                $limit = 15;
            }
        }

        return $src->paginate($limit)
            ->withQueryString();
    }

    public function pageless(string|Builder $src, Request $request, ?array &$meta = []): Builder
    {
        $this->timezone = CarbonTimeZone::create($request->cookies->getString('tz', config('app.timezone')));

        if (is_string($src)) {
            if (is_subclass_of($src, Model::class)) {
                $src = call_user_func([$src, 'query']);
            } else {
                throw new InvalidArgumentException("Source must be a Model class or a Builder instance.");
            }
        }

        $meta = ['filters' => [], 'sort' => null];
        $this->input = in_array($request->getRealMethod(), ['GET', 'HEAD']) ?
            $request->query : ($request->isJson() ? $request->json() : $request->request);

        foreach ($this->filters as $name => $config) {
            $type = $config['type'];
            $fn = 'apply' . ucfirst($type) . 'Filter';
            $parsed = null;
            $this->{$fn}($src, $this->input, $name, $config, $parsed);
            if (!is_null($parsed)) {
                $meta['filters'][$name] = $parsed;
            }
        }

        if (empty($meta['filters'])) {
            // make sure it serializes to an empty object
            $meta['filters'] = (object)[];
        }

        $sortBy = null;
        if ($sort = $this->input->getString('sort')) {
            $sorts = explode(',', $sort);
            if (isset($this->sortable[$sorts[0]])) {
                $sortBy = [$this->sortable[$sorts[0]], $sorts[1] ?? 'asc'];
                $meta['sort'] = [$sorts[0], $sorts[1] ?? 'asc'];
            }
        }
        if (!$sortBy && !empty($this->sortBy)) {
            $sortBy = [$this->sortable[$this->sortBy[0]], $this->sortBy[1]];
            $meta['sort'] = $this->sortBy;
        }
        if ($sortBy) {
            $src->orderBy($sortBy[0], $sortBy[1]);
        }

        return $src;
    }

    private function applyStringFilter(Builder $src, InputBag $input, string $name, array $config, ?string &$parsed): bool
    {
        $default = $config['default'] ?? null;
        $value = $input->get($name);
        if (!is_string($value) || empty($value)) {
            if (!$default) return false;
            $value = $default;
            $valid = false;
        } else {
            $valid = true;
        }
        $parsed = $value;
        call_user_func($config['callback'], $src, $value, $name);
        return $valid;
    }

    private function applyStringsFilter(Builder $src, InputBag $input, string $name, array $config, ?array &$parsed): bool
    {
        $default = $config['default'] ?? null;
        $value = $input->all($name);
        if (!is_array($value) || empty($value)) {
            if (!$default) return false;
            $valid = false;
            $parsed = $default;
        } else {
            $parsed = [];
            foreach ($value as $v) {
                if (is_string($v) && !empty($v)) {
                    $parsed[] = $v;
                }
            }
            if (empty($parsed)) {
                if (!$default) return false;
                $parsed = $default;
            }
            $valid = true;
        }
        call_user_func($config['callback'], $src, $parsed, $name);
        return $valid;
    }

    private function applyIntFilter(Builder $src, InputBag $input, string $name, array $config, ?int &$parsed): bool
    {
        $default = $config['default'] ?? null;
        $value = $input->filter(
            $name, null,
            FILTER_VALIDATE_INT, ['flags' => FILTER_REQUIRE_SCALAR | FILTER_NULL_ON_FAILURE]
        );
        if (is_null($value)) {
            if (is_null($default)) return false;
            $value = $default;
            $valid = false;
        } else {
            $valid = true;
        }
        $parsed = $value;
        call_user_func($config['callback'], $src, $value, $name);
        return $valid;
    }

    private function applyIntsFilter(Builder $src, InputBag $input, string $name, array $config, ?array &$parsed): bool
    {
        $default = $config['default'] ?? null;
        $value = $input->all($name);
        if (!is_array($value)) $value = [$value];
        if (empty($value)) {
            if (is_null($default)) return false;
            $valid = false;
            $parsed = $default;
        } else {
            $parsed = [];
            foreach ($value as $v) {
                if (is_int($v) || ctype_digit($v)) {
                    $parsed[] = intval($v);
                }
            }
            if (empty($parsed)) {
                if (is_null($default)) return false;
                $parsed = $default;
            }
            $valid = true;
        }
        call_user_func($config['callback'], $src, $parsed, $name);
        return $valid;
    }

    private function applyEnumFilter(Builder $src, InputBag $input, string $name, array $config, ?BackedEnum &$parsed): bool
    {
        $default = $config['default'] ?? null;
        $value = $input->getEnum($name, $config['enum'], $default);
        if (is_null($value)) {
            if (is_null($default)) return false;
            $value = $default;
            $valid = false;
        } else {
            $valid = true;
        }
        $parsed = $value;
        call_user_func($config['callback'], $src, $value, $name);
        return $valid;
    }

    private function applyEnumsFilter(Builder $src, InputBag $input, string $name, array $config, ?array &$parsed): bool
    {
        $default = $config['default'] ?? null;
        $value = $input->all($name);
        if (empty($value)) {
            if (is_null($default)) return false;
            $valid = false;
            $parsed = $default;
        } else {
            $parsed = [];
            foreach ($value as $enum) {
                if (!is_string($enum)) {
                    continue;
                }
                $enumValue = $config['enum']::tryFrom($enum);
                if ($enumValue) {
                    $parsed[] = $enumValue;
                }
            }
            if (empty($parsed)) {
                if (is_null($default)) return false;
                $parsed = $default;
            }
            $valid = true;
        }
        call_user_func($config['callback'], $src, $parsed, $name);
        return $valid;
    }

    private function applyNumericFilter(Builder $src, InputBag $input, string $name, array $config, ?array &$parsed = null): bool
    {
        $expr = $input->getString($name, null);
        $column = $config['field'];

        // ───── Parse once with a single, commented regex ─────────────────────────
        static $pattern = '~^
        \s*
        (?:                                           # three exclusive branches
            (?P<range_from>-?\d+(?:\.\d+)?)            # ① range start
            \s*~\s*
            (?P<range_to>  -?\d+(?:\.\d+)?)            #    range end
        |
            (?P<op>>=|<=|>|<)                          # ② comparison operator
            \s*
            (?P<compare>-?\d+(?:\.\d+)?)               #    comparison value
        |
            (?P<single>-?\d+(?:\.\d+)?)                # ③ single number
        )
        \s*
        $~x';

        if (!preg_match($pattern, $expr, $m)) {
            return false;                                  // invalid expression
        }

        // ───── Build the query according to the detected type ───────────────────
        if ($m['single'] !== '') {
            $val = floatval($m['single']);
            $parsed = ['=' => $val];
            $src->where($column, '=', $val);
        } elseif ($m['range_from'] !== '') {
            $from = floatval($m['range_from']);
            $to = floatval($m['range_to']);

            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }

            $parsed = compact('from', 'to');
            $src->whereBetween($column, [$from, $to]);
        } else {
            $val = floatval($m['compare']);
            $parsed = [$m['op'] => $val];
            $src->where($column, $m['op'], $val);
        }

        return true;
    }

    private function applyDateFilter(Builder $src, InputBag $input, string $name, array $config, ?array &$parsed): bool
    {
        $expr = $input->getString($name, null);
        $column = $config['field'];

        // ───── Parse once with a single, commented regex ─────────────────────────
        static $pattern = '~^
        \s*
        (?:
            (?P<range_from>\d{4}-\d{2}-\d{2})
            \s*~\s*
            (?P<range_to>\d{4}-\d{2}-\d{2})
          |
            (?P<op>>=|<=|>|<)
            \s*
            (?P<compare>\d{4}-\d{2}-\d{2})
          |
            (?P<single>\d{4}-\d{2}-\d{2})
        )
        \s*
        $~x';

        if (!preg_match($pattern, $expr, $m)) {
            return false;                                  // invalid expression
        }

        // ───── Build the query according to the detected type ───────────────────
        if ($m['single'] !== '') {
            $parsed = ['=' => $m['single']];
            $start = Carbon::createFromFormat('Y-m-d', $m['single'], $this->timezone)
                ->startOfDay();
            $end = $start->clone()->addDay();
            $src->where(fn($query) => $query
                ->where($column, '>=', $start->setTimezone('UTC'))
                ->where($column, '<', $end->setTimezone('UTC'))
            );
        } elseif ($m['range_from'] !== '') {
            $parsed = [
                'from' => $m['range_from'],
                'to' => $m['range_to'],
            ];
            $start = Carbon::createFromFormat('Y-m-d', $m['range_from'], $this->timezone)
                ->startOfDay();
            $end = Carbon::createFromFormat('Y-m-d', $m['range_to'], $this->timezone)
                ->addDay()->startOfDay();

            $src->where(fn($query) => $query
                ->where($column, '>=', $start->setTimezone('UTC'))
                ->where($column, '<', $end->setTimezone('UTC'))
            );

        } else {
            $parsed = [$m['op'] => $m['compare']];
            if (in_array($m['op'], ['>', '>='])) {
                $start = Carbon::createFromFormat('Y-m-d', $m['compare'], $this->timezone)
                    ->startOfDay();
                if ($m['op'] === '>') {
                    $start = $start->addDay();
                }
                $src->where($column, '>=', $start->setTimezone('UTC'));
            } else {
                $end = Carbon::createFromFormat('Y-m-d', $m['compare'], $this->timezone)
                    ->startOfDay()
                    ->addDay();
                if ($m['op'] === '<') {
                    $end = $end->subDay();
                }
                $src->where($column, '<', $end->setTimezone('UTC'));
            }
        }

        return true;
    }

    public static function make(): static
    {
        return new static();
    }

    public static function filterStringContains(Builder $src, string $value, string $name): void
    {
        $column = $src->qualifyColumn($name);
        $src->whereRaw("LOCATE(?, $column) > 0", [$value]);
    }

    public static function filterValueEquals(Builder $src, string|BackedEnum $value, string $name): void
    {
        $column = $src->qualifyColumn($name);
        $src->where($column, '=', $value);
    }

    public static function filterValueIn(Builder $src, array $values, string $name): void
    {
        if (empty($values)) {
            return;
        }
        $column = $src->qualifyColumn($name);
        $src->whereIn($column, $values);
    }
}
