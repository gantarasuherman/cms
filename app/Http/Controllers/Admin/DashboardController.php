<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Faq;
use App\Models\News;
use App\Models\Service;
use App\Models\User;
use App\Services\Analytics\VisitorStatistics;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(VisitorStatistics $visitors): View
    {
        return view('admin.dashboard', [
            'stats' => [
                ['label' => 'Berita', 'value' => News::count(), 'icon' => 'newspaper', 'route' => 'admin.news.index'],
                ['label' => 'Layanan', 'value' => Service::count(), 'icon' => 'briefcase', 'route' => 'admin.services.index'],
                ['label' => 'Dokumen', 'value' => Document::count(), 'icon' => 'file-text', 'route' => 'admin.documents.index'],
                ['label' => 'FAQ', 'value' => Faq::count(), 'icon' => 'circle-help', 'route' => 'admin.faq.index'],
                ['label' => 'Pengguna', 'value' => User::count(), 'icon' => 'users', 'route' => 'admin.users.index'],
            ],
            'latestNews' => News::with('author')->latest()->limit(5)->get(),

            'analyticsEnabled' => $visitors->enabled(),
            'visitorSummary' => $visitors->enabled() ? $visitors->summary() : null,
            'visitorDaily' => $visitors->enabled() ? $visitors->daily(14) : collect(),
            'topPages' => $visitors->enabled() ? $visitors->topPages() : collect(),
            'topReferrers' => $visitors->enabled() ? $visitors->topReferrers() : collect(),
        ]);
    }
}

