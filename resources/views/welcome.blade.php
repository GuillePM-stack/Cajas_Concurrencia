<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Bank Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        [v-cloak] {
            display: none;
        }

        .sidebar-transition {
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
    </style>
</head>

<body class="bg-white font-sans text-slate-900">

    <div id="app" v-cloak class="flex h-screen overflow-hidden">

        <aside :class="sidebarAbierto ? 'w-72 border-r border-zinc-800' : 'w-0'"
            class="sidebar-transition bg-zinc-800 text-zinc-300 flex flex-col relative z-20 overflow-hidden">
            <div class="w-72 flex flex-col h-full">
                <div class="p-6 flex items-center justify-between">
                    <h2 class="text-xl font-medium text-white px-2">Cajas</h2>
                    <button @click="crearNuevaCaja" class="p-2 hover:bg-zinc-800 rounded-full text-zinc-400 hover:text-white transition-colors" title="Nueva caja">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                    </button>
                </div>

                <nav class="flex-1 overflow-y-auto px-3 mt-2">
                    <button v-for="c in listaCajas" :key="c" @click="seleccionarCaja(c)"
                        :class="['w-full text-left px-4 py-3 rounded-full text-sm flex items-center gap-3', caja === c ? 'bg-[#1e1f20] text-white shadow-sm' : 'hover:bg-[#1e1f20]']">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="4" width="20" height="16" rx="2" />
                            <path d="M7 15h0M2 9.5h20" />
                        </svg>
                        <span>Caja #@{{ c }}</span>
                    </button>
                </nav>
            </div>
        </aside>

        <main class="flex-1 overflow-y-auto bg-white relative">
            <div class="absolute top-6 left-6 z-30">
                <button @click="sidebarAbierto = !sidebarAbierto" class="p-2.5 bg-white border border-slate-200 rounded-xl shadow-sm hover:bg-slate-100">
                    <svg v-if="sidebarAbierto" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m15 18-6-6 6-6" />
                    </svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="12" x2="21" y2="12" />
                        <line x1="3" y1="6" x2="21" y2="6" />
                        <line x1="3" y1="18" x2="21" y2="18" />
                    </svg>
                </button>
            </div>

            <div class="max-w-4xl mx-auto px-8 py-24">
                <header class="mb-12">
                    <h1 class="text-4xl font-bold text-slate-900 mb-2">Caja #@{{ caja }}</h1>
                </header>

                <section class="mb-10">
                    <div class="bg-slate-50 border border-slate-200 rounded-3xl p-8 shadow-sm">
                        <label class="block text-[10px] font-bold uppercase text-slate-400 mb-3">Monto</label>
                        <div class="flex items-center gap-4">
                            <span class="text-3xl text-slate-300">$</span>
                            <input type="number" v-model="importe" class="bg-transparent text-4xl font-light w-full outline-none border-none focus:ring-0" placeholder="0">
                        </div>
                    </div>
                </section>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-10">
                    <x-boton-cajero @click="abrirCaja" v-bind:disabled="disabledAbrir">Abrir caja</x-boton-cajero>
                    <x-boton-cajero @click="cambiarCheque">Cambiar Cheque</x-boton-cajero>
                    <x-boton-cajero @click="agregarBilletes">Agregar billetes</x-boton-cajero>
                </div>

                <span class="font-bold mb-8 block">Movimientos de la bodega</span>

                <div v-if="billetes && billetes.length > 0" class="border border-slate-200 rounded-3xl overflow-hidden shadow-sm">

                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-500 uppercase text-[10px] font-bold tracking-widest border-b border-slate-100">
                            <tr>
                                <th class="px-8 py-5">Denominación</th>
                                <th class="px-8 py-5 text-center">Entregados</th>
                                <th class="px-8 py-5 text-right">Existencia</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="billete in billetes" :key="billete.denominacion">
                                <td class="px-8 py-5 font-bold text-slate-700 text-lg">$@{{ billete.denominacion }}</td>
                                <td class="px-8 py-5 text-center text-slate-600">@{{ billete.entregados ?? 0 }}</td>
                                <td class="px-8 py-5 text-right font-mono text-slate-400">@{{ billete.existencia ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    @vite('resources/js/app.js')
</body>

</html>