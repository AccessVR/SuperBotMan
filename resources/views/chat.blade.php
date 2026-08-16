<!doctype html>
<html>
<head>
    <title>SuperBotMan Chat</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- laravel-echo reads this for the X-CSRF-TOKEN header on private-channel
         auth requests; without it every /broadcasting/auth call 419s. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" type="text/css" href="{{ \OrchestrateXR\SuperBotMan\Facades\SuperBotMan::asset('resources/css/common.css') }}">
</head>
<body class="relative flex flex-col p-0">
    <div id="chat" v-cloak></div>
    <script>
        window.superbotmanWidget = @json(\OrchestrateXR\SuperBotMan\Facades\SuperBotMan::getClientConfig($config))
    </script>
    <script type="module" src="{{ \OrchestrateXR\SuperBotMan\Facades\SuperBotMan::asset('resources/js/chat.js') }}"></script>
</body>
</html>
