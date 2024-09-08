<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

    <x-page-head/>

    <body class="flex h-screen w-screen flex-col antialiased font-sans">

        <main class="flex h-full w-full items-center justify-center bg-gray-100">

            <!-- Title Card -->
            <div class="flex flex-col items-center justify-center rounded-lg bg-white p-6 shadow-lg">
                <div class="w-4/5 border-b-2 pb-4">
                    <x-application-logo class="mx-auto w-40" />
                </div>
                <h1 class="pt-4 text-center text-xl">Portfolio Management Platform</h1>

                <div class="flex justify-evenly mx-auto space-x-2 mt-4">
                    <a href="{{ route('login') }}"><x-primary-button class="text-center">Login</x-primary-button></a>
                    <a href="{{ route('register') }}"><x-secondary-button class="text-center">Register</x-secondary-button></a>
                </div>
            </div>

        </main>

    </body>
</html>
