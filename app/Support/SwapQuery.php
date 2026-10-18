<?php

namespace App\Support;

use App\Models\CardSwap;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SwapQuery
{
    public function visible(Request $request): Builder
    {
        $company = $request->attributes->get('company');
        abort_unless($company && $request->user()->can('administer', [$company, 'swaps.view']), 403);

        return CardSwap::where('company_id', $company->id)->when(! $request->user()->can('swaps.view-all'), fn ($q) => $q->where('created_by', $request->user()->id));
    }

    public function filters(Builder $query, Request $request): Builder
    {
        $request->validate(['search' => ['nullable', 'string', 'max:180'], 'search_by' => ['nullable', 'in:reference,national_id,old_card,new_card,name'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'], 'status' => ['nullable', 'in:'.implode(',', CardSwap::STATUSES)], 'reason' => ['nullable', 'in:lost,damaged,faulty,other'], 'staff' => ['nullable', 'integer'], 'location' => ['nullable', 'integer']]);

        return $query->when($request->filled('search'), function ($q) use ($request) {
            $by = $request->input('search_by', 'reference');
            $value = trim($request->input('search'));
            $by === 'reference' ? $q->where('reference', $value) : $q->where($by === 'name' ? 'name_hash' : $by.'_hash', CardSwap::fingerprint($value));
        })->when($request->filled('from'), fn ($q) => $q->whereDate('swap_date', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('swap_date', '<=', $request->input('to')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('reason'), fn ($q) => $q->where('reason', $request->input('reason')))
            ->when($request->filled('staff'), fn ($q) => $q->where('created_by', $request->integer('staff')))
            ->when($request->filled('location'), fn ($q) => $q->where('location_id',$request->integer('location')));
    }
}
