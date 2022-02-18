<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class GetListCompanyAndCostController extends Controller
{
    private function getCompanyTravel(): Collection
    {
        if (!Cache::get('travel')) {
            $client = new Client();
            $data = $client->get(config('app.travel_url'));
            $data = collect(json_decode($data->getBody()->getContents(), true));
            Cache::add('travel', $data);
        }
        return Cache::get('travel');
    }

    private function getCompanyList(): Collection
    {
        if (!Cache::get('company')) {
            $client = new Client();
            $data = $client->get(config('app.company_url'));
            $data = collect(array_filter(json_decode($data->getBody()->getContents(), true), function ($company) {
                return $company['id'] != 'NaN' && $company['parentId'] != 'uuid-{{i}}';
            }));
            Cache::add('company', $data);
        }
        return Cache::get('company');
    }

    private function getCompanyFormatted()
    {
        $companyList = $this->getCompanyList();
        $formatCompany = [];
        $companyList->each(function ($company) use (&$formatCompany) {
            $formatCompany[$company['parentId']][] = $company;
        });
        $rootParent = $companyList->filter(function ($company) {
            return $company['parentId'] == 0;
        })->first();
        return $this->formatCompanyTree($formatCompany, [$rootParent]);
    }

    private function formatCompanyTree(&$list, $parentCompany)
    {
        $buildTree = [];
        foreach ($parentCompany as $parent) {
            if (isset($list[$parent['id']])) {
                $parent['children'] = $this->formatCompanyTree($list, $list[$parent['id']]);
            }
            unset($parent['parentId']);
            unset($parent['createdAt']);
            $buildTree[] = $parent;
        }
        return $buildTree;
    }

    private function getTravelCost(&$company)
    {
        $travels = $this->getCompanyTravel();
        $cost = 0;
        foreach ($travels as $travel) {
            if ($travel['companyId'] == $company['id']) {
                $cost += floatval($travel['price']);
                $company['cost'] = $cost;
            }
        }
        if (!empty($company['children'])) {
            foreach ($company['children'] as &$child) {
                $this->getTravelCost($child);
                $company['cost'] += $child['cost'];
            }
        }
        return $company;
    }

    public function execute()
    {
        $company = $this->getCompanyFormatted();
        foreach ($company as &$v) {
            $this->getTravelCost($v);
        }
        return response()->json($company);
    }
}
