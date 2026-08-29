<?php

namespace NovinVision\DataTable\Services;

use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use NovinVision\DataTable\Engine\Html;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use function Clue\StreamFilter\fun;

class DataTable extends \Yajra\DataTables\Services\DataTable
{
    protected ?QueryBuilder $builder = null;

    protected array $columns = [];

    protected static array $searchQueryLogics = [
        "=", "!=", "starts", "!starts", "contains", "!contains", "ends", "!ends", "null", "!null"
    ];

    protected int $orderBy = 0;

    protected bool $enableSearchBuilder = false;

    public function __construct()
    {
        parent::__construct();

        $this->responseCallback = function ($data) {
            $data['data'] = collect($data['data'] ?? [])->map(function ($row, $rowID) {
                return collect($row)->filter(function ($value, $key) {
                    return $key == 'id' || in_array($key, collect($this->getColumns())->pluck('name')->toArray());
                });
            })->toArray();

            return $data;
        };
    }

    public function isDataTableRequest(): bool
    {
        return $this->request()->isXmlHttpRequest() && $this->request()->input('draw');
    }

    public function renderInertia($component, array $data = []): \Illuminate\Http\JsonResponse|\Inertia\Response
    {
        if ($this->isDatatableRequest()) {
            return $this->ajax();
        }

        return Inertia::render($component, array_merge($data, [
            'datatable' => [
                'columns' => $this->getColumns(),
                'tabs' => $this->getTabs(),
                'batches' => $this->batches(),
                'options' => $this->html()->getOptions(),
            ]
        ]));
    }

    public function dataTable(QueryBuilder $query): \Yajra\DataTables\DataTableAbstract
    {
        return (new EloquentDataTable($query))
            ->editColumn('status', function (Model $model) {
                return method_exists($model, 'getStatusBadge') ? $model->getStatusBadge() ?? $model->status : '';
            })
            ->editColumn('updated_at', function (Model $model) {
                return $model->updated_at?->ago ?? $model->updated_at;
            })
            ->editColumn('created_at', function (Model $model) {
                return $model->created_at?->ago ?? $model->created_at;
            });
    }

    public function searchBuilderQuery(): \Illuminate\Support\Collection
    {
        $validator = Validator::make($this->request()->input('searchBuilder', []), [
            'logic' => ['required', 'in:AND,OR'],

            'criteria.*.logic' => ['nullable', 'string', 'in:AND,OR'],
            'criteria.*.condition' => ['required', 'string', 'in:' . implode(',', self::$searchQueryLogics)],
            'criteria.*.data' => ['string',],
            'criteria.*.origData' => ['string'],
            'criteria.*.value1' => ['nullable', 'string'],

            'criteria.*.criteria' => ['nullable', 'array'],
            'criteria.*.criteria.*.condition' => ['required', 'string', 'in:' . implode(',', self::$searchQueryLogics)],
            'criteria.*.criteria.*.data' => ['required', 'string',],
            'criteria.*.criteria.*.origData' => ['required', 'string'],
            'criteria.*.criteria.*.value1' => ['required', 'nullable', 'string'],
        ]);

        return collect($validator->validated());
    }

    public function setBuilder(QueryBuilder $builder): static
    {
        $this->builder = $builder;
        return $this;
    }

    public function queryBuilder(Model $model): QueryBuilder
    {
        if ($this->builder) {
            return $this->builder;
        }

        return $model->newQuery();
    }

    public function columnSearchValue($column)
    {
        return collect($this->request()->columns())->where('name', $column)->first()?->search ?? null;
    }

    public function batches(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [];
    }

    public function checkboxColumn(): Column
    {
        return Column::checkbox('<input type="checkbox" class="select-all-checkbox align-middle" />')->className('select-checkbox text-center');
    }

    public function openColumnPlus(): Column
    {
        return Column::checkbox('')->className('open-row text-center');
    }

    public function setOrderColumn(int $columnID): static
    {
        $this->orderBy = $columnID;
        return $this;
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId(basename(get_class($this)))
            ->processing()
            ->serverSide()
            ->rowGroup(false)
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy($this->orderBy)
            ->buttons([
                \Yajra\DataTables\Html\Button::make('colvis')->className('btn-sm rounded-5')->text('نمایش ستون'),
            ])
            ->dom('Q<"datatable-search-panes-collapse"P><"row g-2 align-items-center mb-2"<"col-auto me-auto batch order-1 order-sm-first small text-muted"i><"col-12 col-md-auto fanum ms-auto text-muted text-center order-first order-sm-2"B><"col col-md-auto order-3"f><"col-auto">><"position-relative"r<"table-responsive-xs table-responsive-sm table-responsive-md"t>><"row align-items-center border-top pt-3 mt-3"<"col-auto col-md-auto fanum"l><"col-auto col-sm ms-auto ms-sm-0 fanum my-2 my-lg-0 small text-muted"i><"col d-none d-lg-block"><"col-12 col-sm-auto fanum mt-2 mt-sm-0"p>>');
    }

    public function isEnableSearchBuilder(): bool
    {
        return $this->enableSearchBuilder;
    }

    public function setEnableSearchBuilder(bool $enableSearchBuilder): static
    {
        $this->enableSearchBuilder = $enableSearchBuilder;
        return $this;
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'export_' . date('YmdHis');
    }

    public function setColumns(array $columns = []): static
    {
        $this->columns = $columns;

        return $this;
    }

    public function defineColumns(array $columns = []): array
    {
        if ($this->columns) {
            return $this->authorizedColumns([
                ...$this->columns,
            ]);
        }

        return $this->authorizedColumns([
            ...$columns,
        ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->title('شماره'),
            Column::make('created_at')->title('زمان ایجاد'),
        ];
    }

    public function authorizedColumns(array $columns = []): array
    {
        return collect($columns)->filter(function (Column $column) {
            return $column->isAuthorized();
        })->toArray();
    }

    public function actionColumn(): Column
    {
        return Column::computed('action')->title('')->orderable(false)->searchable(false);
    }

    public function button($text, $url, $attrs = []): string
    {
        return Html::createLink($text, $url, $attrs);
    }

    public function viewButton($text, $url, $attrs = []): string
    {
        return Html::createLink("<i class=\"fal fa-circle-info align-middle me-1\"></i> {$text}", $url, array_merge($attrs, [
            'class' => 'btn btn-secondary btn-sm',
        ]));
    }

    public function successButton($text, $url, $attrs = []): string
    {
        return Html::createLink("<i class=\"fal fa-check align-middle me-1\"></i> {$text}", $url, [
            'class' => 'btn btn-success btn-sm',
            ...$attrs,
        ]);
    }

    public function deleteButton($text, $url, $attrs = []): string
    {
        return Html::createLink("<i class=\"fal fa-trash align-middle me-1\"></i> {$text}", $url, [
            'class' => 'btn btn-danger btn-sm',
            'data-click' => 'confirm',
            ...$attrs,
        ]);
    }

    public function confirmButton($text, $url, $attrs = []): string
    {
        return Html::createLink("<i class=\"fal fa-check align-middle me-1\"></i> {$text}", $url, [
            'class' => 'btn btn-danger btn-sm',
            'data-click' => 'confirm',
            ...$attrs,
        ]);
    }

    public function confirmSuccessButton($text, $url, $attrs = []): string
    {
        return $this->confirmButton($text, $url, [
            'class' => 'btn btn-success btn-sm',
            ...$attrs
        ]);
    }

}
