<!DOCTYPE html><html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $branding['report_title']?:__('Operational report') }}</title>
@if(!($pdfMode??false))@fonts('montserrat')@endif
<style>
@if($pdfMode??false)
@font-face{font-family:Montserrat;src:url("{{ resource_path('fonts/Montserrat-Regular.ttf') }}") format("truetype");font-weight:400}
@font-face{font-family:Montserrat;src:url("{{ resource_path('fonts/Montserrat-Bold.ttf') }}") format("truetype");font-weight:700}
@endif
@page{margin:36px}*{box-sizing:border-box}body{font-family:Montserrat,DejaVu Sans,sans-serif;color:#172B4D;font-size:12px;line-height:1.6;margin:0;padding:24px}h1{font-size:23px;margin:0}h2{font-size:17px;margin-top:28px}p{margin:6px 0}.document-logo{max-width:180px;max-height:90px;margin-bottom:14px}.document-company{font-size:22px}.document-address,.document-footer{white-space:pre-wrap}.document-header{border-bottom:2px solid #1747A6;padding-bottom:20px}.document-footer{border-top:1px solid #dce6ef;margin-top:30px;padding-top:14px;font-size:10px}table{border-collapse:collapse;width:100%;margin-top:20px}th,td{padding:12px;border-bottom:1px solid #dce6ef;text-align:left}th{font-weight:700;background:#F3F8FC}.tools{margin-bottom:24px}.tools button,.tools a{padding:12px;border:1px solid #ccd9e6;border-radius:8px;background:white;color:#1747A6;text-decoration:none;font:inherit}small{color:#63758e} @media print{.tools{display:none}body{padding:0}}@media(max-width:500px){body{padding:16px;font-size:11px}h1{font-size:20px}th,td{padding:8px}}
</style></head><body>
@if(!($pdfMode??false))<div class="tools"><button type="button" onclick="window.print()">{{ __('Print report') }}</button> <a href="{{ route('reports.index',request()->query()) }}">{{ __('Back to Reports') }}</a></div>@endif
@include('documents.header')
<h2>{{ $branding['report_title']?:__('Operational report') }}</h2>
<p>{{ __('Generated at') }}: {{ now()->format('d M Y H:i') }} {{ config('app.timezone') }}</p>
<p>{{ __('Period') }}: {{ request('from')?:__('All dates') }} — {{ request('to')?:__('All dates') }}</p>
@foreach(['status'=>'Status','reason'=>'Reason'] as $key=>$label)@if(request()->filled($key))<p>{{ __($label) }}: {{ __(request($key)) }}</p>@endif @endforeach
<table><thead><tr><th>{{ __('Category') }}</th><th>{{ __('Count') }}</th></tr></thead><tbody>@foreach(['total'=>'Total acknowledgements','completed'=>'Completed','pending'=>'Pending','unsuccessful'=>'Unsuccessful','completion_rate'=>'Completion rate'] as $key=>$label)<tr><td>{{ __($label) }}</td><td>{{ $summary[$key] }}{{ $key==='completion_rate'?'%':'' }}</td></tr>@endforeach</tbody></table>
<p><small>{{ __('This report contains company-level aggregates. Customer identification and signatures are not included.') }}</small></p>
@include('documents.footer')<p><small>iYawe · {{ __('Digital acknowledgements, made simple.') }}</small></p>
</body></html>
