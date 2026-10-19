<!doctype html><html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="markdown-fixture-only">
{{-- The UI fixture must never let the PWA prefetch production application routes. --}}
<script>delete Navigator.prototype.serviceWorker;</script>
@include('partials.app-styles')
@include('partials.app-scripts')
@livewireStyles
<style>[x-cloak]{display:none!important}body{padding:16px;max-width:900px;margin:auto}.markdown-fixture-dialog{margin:16px 0}.markdown-fixture-dialog>button{margin:2px}</style>
</head><body>
<livewire:markdown-fixture />@livewireScriptConfig</body></html>
