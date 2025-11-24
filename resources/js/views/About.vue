<template>
    <AppLayout>
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-lg shadow-xl p-8 text-white mb-6">
                <div class="flex items-center justify-center mb-6">
                    <img :src="logoUrl" alt="FOS Streaming Reborn" class="h-20 w-auto max-w-xs object-contain bg-white rounded-lg p-3">
                </div>
                <h1 class="text-4xl font-bold text-center mb-2">FOS-Streaming Reborn</h1>
                <p class="text-xl text-center text-indigo-100">Enterprise IPTV SaaS Platform</p>
                <p class="text-center text-indigo-200 mt-4">Version 70.0.0 - Security Fortress</p>
            </div>

            <!-- Quick Links -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <!-- GitHub -->
                <a href="https://github.com/CristianCasapu/FOS-Streaming-Reborn" target="_blank"
                   class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow duration-200 flex flex-col items-center text-center group">
                    <div class="bg-gray-900 text-white rounded-full p-4 mb-4 group-hover:bg-gray-700 transition-colors">
                        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">GitHub Repository</h3>
                    <p class="text-sm text-gray-600">View source code, report issues, contribute</p>
                </a>

                <!-- Telegram -->
                <a href="https://t.me/CristianCasapu" target="_blank"
                   class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow duration-200 flex flex-col items-center text-center group">
                    <div class="bg-blue-500 text-white rounded-full p-4 mb-4 group-hover:bg-blue-600 transition-colors">
                        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.446 1.394c-.14.18-.357.223-.548.223l.188-2.85 5.18-4.68c.223-.198-.054-.308-.346-.11l-6.4 4.03-2.76-.918c-.6-.187-.612-.6.125-.89l10.782-4.156c.504-.187.943.112.78.89z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Contact Developer</h3>
                    <p class="text-sm text-gray-600">Get support, ask questions on Telegram</p>
                </a>

                <!-- Buy Me a Coffee -->
                <a href="https://buymeacoffee.com/CristianCasapu" target="_blank"
                   class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow duration-200 flex flex-col items-center text-center group">
                    <div class="bg-yellow-500 text-white rounded-full p-4 mb-4 group-hover:bg-yellow-600 transition-colors">
                        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M20.216 6.415l-.132-.666c-.119-.598-.388-1.163-1.001-1.379-.197-.069-.42-.098-.57-.241-.152-.143-.196-.366-.231-.572-.065-.378-.125-.756-.192-1.133-.057-.325-.102-.69-.25-.987-.195-.4-.597-.634-.996-.788a5.723 5.723 0 00-.626-.194c-1-.263-2.05-.36-3.077-.416a25.834 25.834 0 00-3.7.062c-.915.083-1.88.184-2.75.5-.318.116-.646.256-.888.501-.297.302-.393.77-.177 1.146.154.267.415.456.692.58.36.162.737.284 1.123.366 1.075.238 2.189.331 3.287.37 1.218.05 2.437.01 3.65-.118.299-.033.598-.073.896-.119.352-.054.578-.513.474-.834-.124-.383-.457-.531-.834-.473-.466.074-.96.108-1.382.146-1.177.08-2.358.082-3.536.006a22.228 22.228 0 01-1.157-.107c-.086-.01-.18-.025-.258-.036-.243-.036-.484-.08-.724-.13-.111-.027-.111-.185 0-.212h.005c.277-.06.557-.108.838-.147h.002c.131-.009.263-.032.394-.048a25.076 25.076 0 013.426-.12c.674.019 1.347.067 2.017.144l.228.031c.267.04.533.088.798.145.392.085.895.113 1.07.542.055.137.08.288.111.431l.319 1.484a.237.237 0 01-.199.284h-.003c-.037.006-.075.01-.112.015a36.704 36.704 0 01-4.743.295 37.059 37.059 0 01-4.699-.304c-.14-.017-.293-.042-.417-.06-.326-.048-.649-.108-.973-.161-.393-.065-.768-.032-1.123.161-.29.16-.527.404-.675.701-.154.316-.199.66-.267 1-.069.34-.176.707-.135 1.056.087.753.613 1.365 1.37 1.502a39.69 39.69 0 0011.343.376.483.483 0 01.535.53l-.071.697-1.018 9.907c-.041.41-.047.832-.125 1.237-.122.637-.553 1.028-1.182 1.171-.577.131-1.165.2-1.756.205-.656.004-1.31-.025-1.966-.022-.699.004-1.556-.06-2.095-.58-.475-.458-.54-1.174-.605-1.793l-.731-7.013-.322-3.094c-.037-.351-.286-.695-.678-.678-.336.015-.718.3-.678.679l.228 2.185.949 9.112c.147 1.344 1.174 2.068 2.446 2.272.742.12 1.503.144 2.257.156.966.016 1.942.053 2.892-.122 1.408-.258 2.465-1.198 2.616-2.657.34-3.332.683-6.663 1.024-9.995l.215-2.087a.484.484 0 01.39-.426c.402-.078.787-.212 1.074-.518.455-.488.546-1.124.385-1.766zm-1.478.772c-.145.137-.363.201-.578.233-2.416.359-4.866.54-7.308.46-1.748-.06-3.477-.254-5.207-.498-.17-.024-.353-.055-.47-.18-.22-.236-.111-.71-.054-.995.052-.26.152-.609.463-.646.484-.057 1.046.148 1.526.22.577.088 1.156.159 1.737.212 2.48.226 5.002.19 7.472-.14.45-.06.899-.13 1.345-.21.399-.072.84-.206 1.08.206.166.281.188.657.162.974a.544.544 0 01-.169.364zm-6.159 3.9c-.862.37-1.84.788-3.109.788a5.884 5.884 0 01-1.569-.217l.877 9.004c.065.78.717 1.38 1.5 1.38 0 0 1.243.065 1.658.065.447 0 1.786-.065 1.786-.065.783 0 1.434-.6 1.499-1.38l.94-9.95a3.996 3.996 0 00-1.322-.238c-.826 0-1.491.284-2.26.613z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Support Project</h3>
                    <p class="text-sm text-gray-600">Buy me a coffee to keep development going</p>
                </a>
            </div>

            <!-- Project Description -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center">
                    <svg class="w-6 h-6 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    About This Project
                </h2>
                <p class="text-gray-700 mb-4 leading-relaxed">
                    FOS-Streaming Reborn is an <strong>enterprise-grade secured IPTV service platform</strong> designed to provide ISP-resistant streaming through advanced encryption (SRT), traffic obfuscation (V2Ray), and modern protocols (QUIC/HTTP3). Built as a multi-tenant SaaS solution with zero-trust security architecture.
                </p>
                <div class="bg-indigo-50 border-l-4 border-indigo-600 p-4 rounded">
                    <p class="text-indigo-900 font-semibold mb-2">Core Mission:</p>
                    <p class="text-indigo-800">
                        Transform FOS-Streaming into a secured IPTV service platform that provides enterprise-grade security for content protection, ISP-resistant streaming through traffic obfuscation, multi-tenant SaaS architecture for resellers, and zero-trust security model with complete audit trails.
                    </p>
                </div>
            </div>

            <!-- Technology Stack -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center">
                    <svg class="w-6 h-6 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                    </svg>
                    Technology Stack
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-2">Backend</h3>
                        <ul class="space-y-1 text-gray-700">
                            <li class="flex items-center">
                                <span class="w-2 h-2 bg-indigo-600 rounded-full mr-2"></span>
                                PHP 8.4 + Laravel Components
                            </li>
                            <li class="flex items-center">
                                <span class="w-2 h-2 bg-indigo-600 rounded-full mr-2"></span>
                                Eloquent ORM
                            </li>
                            <li class="flex items-center">
                                <span class="w-2 h-2 bg-indigo-600 rounded-full mr-2"></span>
                                MariaDB 11.4 (UTF8MB4)
                            </li>
                            <li class="flex items-center">
                                <span class="w-2 h-2 bg-indigo-600 rounded-full mr-2"></span>
                                PM2 + Node.js 20 LTS Workers
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-2">Frontend</h3>
                        <ul class="space-y-1 text-gray-700">
                            <li class="flex items-center">
                                <span class="w-2 h-2 bg-purple-600 rounded-full mr-2"></span>
                                Vue.js 3 (Composition API)
                            </li>
                            <li class="flex items-center">
                                <span class="w-2 h-2 bg-purple-600 rounded-full mr-2"></span>
                                Vue Router + Pinia
                            </li>
                            <li class="flex items-center">
                                <span class="w-2 h-2 bg-purple-600 rounded-full mr-2"></span>
                                TailwindCSS
                            </li>
                            <li class="flex items-center">
                                <span class="w-2 h-2 bg-purple-600 rounded-full mr-2"></span>
                                Vite 7 + PostCSS
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-2">Streaming</h3>
                        <ul class="space-y-1 text-gray-700">
                            <li class="flex items-center">
                                <span class="w-2 h-2 bg-green-600 rounded-full mr-2"></span>
                                Nginx with HTTP-FLV module
                            </li>
                            <li class="flex items-center">
                                <span class="w-2 h-2 bg-green-600 rounded-full mr-2"></span>
                                FFmpeg (latest)
                            </li>
                            <li class="flex items-center">
                                <span class="w-2 h-2 bg-green-600 rounded-full mr-2"></span>
                                RTMP/HLS/HTTP-FLV
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-2">Security (Upcoming)</h3>
                        <ul class="space-y-1 text-gray-700">
                            <li class="flex items-center">
                                <span class="w-2 h-2 bg-red-600 rounded-full mr-2"></span>
                                SRT with AES-256
                            </li>
                            <li class="flex items-center">
                                <span class="w-2 h-2 bg-red-600 rounded-full mr-2"></span>
                                QUIC/HTTP3 + TLS 1.3
                            </li>
                            <li class="flex items-center">
                                <span class="w-2 h-2 bg-red-600 rounded-full mr-2"></span>
                                V2Ray Traffic Obfuscation
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Key Features -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center">
                    <svg class="w-6 h-6 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Key Features
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-gray-700">Modern Vue.js 3 SPA Admin Panel</span>
                    </div>
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-gray-700">RTMP/HLS/HTTP-FLV Streaming</span>
                    </div>
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-gray-700">Multi-tenant Subscriber Management</span>
                    </div>
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-gray-700">Advanced Security (UFW + fail2ban)</span>
                    </div>
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-gray-700">Real-time Activity Monitoring</span>
                    </div>
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-gray-700">PM2 Background Workers</span>
                    </div>
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-gray-700">Stream Import & FFprobe Analysis</span>
                    </div>
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-gray-700">Reseller Management Portal</span>
                    </div>
                </div>
            </div>

            <!-- Developer Info -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center">
                    <svg class="w-6 h-6 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    Developer
                </h2>
                <div class="flex items-center space-x-4">
                    <div class="flex-shrink-0">
                        <div class="w-16 h-16 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-full flex items-center justify-center text-white text-2xl font-bold">
                            CC
                        </div>
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold text-gray-900">Cristian Casapu</h3>
                        <p class="text-gray-600">Full-Stack Developer & Maintainer</p>
                        <div class="flex space-x-4 mt-2">
                            <a href="https://t.me/CristianCasapu" target="_blank" class="text-blue-600 hover:text-blue-700 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.446 1.394c-.14.18-.357.223-.548.223l.188-2.85 5.18-4.68c.223-.198-.054-.308-.346-.11l-6.4 4.03-2.76-.918c-.6-.187-.612-.6.125-.89l10.782-4.156c.504-.187.943.112.78.89z"/>
                                </svg>
                                Telegram
                            </a>
                            <a href="https://github.com/CristianCasapu" target="_blank" class="text-gray-900 hover:text-gray-700 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                    <path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd" />
                                </svg>
                                GitHub
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- License & Credits -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center">
                    <svg class="w-6 h-6 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    License & Credits
                </h2>
                <p class="text-gray-700 mb-4">
                    <strong>License:</strong> All Rights Reserved - FOS-Streaming
                </p>
                <div class="border-t border-gray-200 pt-4">
                    <h3 class="font-semibold text-gray-900 mb-2">Built With:</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-sm text-gray-600">
                        <div>• FFmpeg</div>
                        <div>• Nginx</div>
                        <div>• nginx-http-flv-module</div>
                        <div>• MariaDB</div>
                        <div>• PHP</div>
                        <div>• Vue.js</div>
                        <div>• Laravel Components</div>
                        <div>• TailwindCSS</div>
                        <div>• Vite</div>
                    </div>
                </div>
            </div>

            <!-- Footer Note -->
            <div class="text-center text-gray-600 text-sm mt-6">
                <p>Copyright © 2025 FOS-Streaming. All Rights Reserved.</p>
                <p class="mt-2">Built with ❤️ by Cristian Casapu</p>
            </div>
        </div>
    </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../components/AppLayout.vue';
import { settingsAPI } from '../services/api';

// Logo URL
const logoUrl = ref('/assets/logo-default.svg');

// Load logo from settings
onMounted(async () => {
    try {
        const response = await settingsAPI.get();
        if (response.data.success && response.data.data.logourl) {
            logoUrl.value = response.data.data.logourl;
        }
    } catch (error) {
        console.error('Failed to load logo:', error);
        // Fail silently - use default logo
    }
});
</script>
