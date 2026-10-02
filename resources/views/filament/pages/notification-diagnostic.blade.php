<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">

        {{-- Panel de Estado del Sistema --}}
        <div style="background-color: var(--fi-color-gray-50, #f9fafb); border: 1px solid var(--fi-color-gray-200, #e5e7eb); border-radius: 0.75rem; padding: 1.25rem;">
            <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.75rem; color: var(--fi-color-gray-900, #111827);">
                Estado del Entorno y Configuración
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.75rem; font-size: 0.875rem;">
                <div style="background: white; padding: 0.75rem; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                    <div style="color: #6b7280; font-size: 0.75rem;">APP_ENV</div>
                    <div style="font-weight: 600;">{{ config('app.env') }}</div>
                </div>
                <div style="background: white; padding: 0.75rem; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                    <div style="color: #6b7280; font-size: 0.75rem;">APP_DEBUG</div>
                    <div style="font-weight: 600;">{{ config('app.debug') ? 'true' : 'false' }}</div>
                </div>
                <div style="background: white; padding: 0.75rem; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                    <div style="color: #6b7280; font-size: 0.75rem;">SESSION DRIVER</div>
                    <div style="font-weight: 600;">{{ config('session.driver') }}</div>
                </div>
                <div style="background: white; padding: 0.75rem; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                    <div style="color: #6b7280; font-size: 0.75rem;">CACHE STORE</div>
                    <div style="font-weight: 600;">{{ config('cache.default') }}</div>
                </div>
                <div style="background: white; padding: 0.75rem; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                    <div style="color: #6b7280; font-size: 0.75rem;">SESSION ID (Actual)</div>
                    <div style="font-weight: 600; font-family: monospace; font-size: 0.75rem; word-break: break-all;">
                        {{ session()->getId() }}
                    </div>
                </div>
                <div style="background: white; padding: 0.75rem; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                    <div style="color: #6b7280; font-size: 0.75rem;">Notificaciones en Sesión</div>
                    <div style="font-weight: 600;">
                        {{ count(session('filament.notifications') ?? []) }} elemento(s)
                    </div>
                </div>
            </div>

            @if ($lastActionMessage)
                <div style="margin-top: 1rem; padding: 0.75rem 1rem; background-color: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500;">
                    <strong>Último resultado:</strong> {{ $lastActionMessage }}
                </div>
            @endif
        </div>

        {{-- Batería de Pruebas --}}
        <div style="background-color: white; border: 1px solid var(--fi-color-gray-200, #e5e7eb); border-radius: 0.75rem; padding: 1.25rem;">
            <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--fi-color-gray-900, #111827);">
                Batería de Pruebas de Notificaciones
            </h3>
            <p style="font-size: 0.875rem; color: #6b7280; margin-bottom: 1.25rem;">
                Haz clic en cada botón para probar el flujo de alertas por diferentes canales y métodos de envío.
            </p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">

                {{-- Test 1 --}}
                <div style="border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; display: flex; flex-direction: column; justify-content: space-between; gap: 0.75rem;">
                    <div>
                        <div style="font-weight: 600; font-size: 0.95rem; color: #16a34a;">Test 1: Alerta Simple (Servidor)</div>
                        <div style="font-size: 0.8rem; color: #6b7280;">Usa <code>Notification::make()->success()->send()</code> estándar de Filament.</div>
                    </div>
                    <x-filament::button wire:click="testSimpleNotification" color="success">
                        Lanzar Alerta Simple
                    </x-filament::button>
                </div>

                {{-- Test 2 --}}
                <div style="border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; display: flex; flex-direction: column; justify-content: space-between; gap: 0.75rem;">
                    <div>
                        <div style="font-weight: 600; font-size: 0.95rem; color: #dc2626;">Test 2: Alerta con Botón (Captura Ágil)</div>
                        <div style="font-size: 0.8rem; color: #6b7280;">Usa <code>Notification::make()->actions([Action::make()->button()])->send()</code> idéntico a Captura Ágil.</div>
                    </div>
                    <x-filament::button wire:click="testNotificationWithButton" color="danger">
                        Lanzar Alerta con Botón
                    </x-filament::button>
                </div>

                {{-- Test 3 --}}
                <div style="border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; display: flex; flex-direction: column; justify-content: space-between; gap: 0.75rem;">
                    <div>
                        <div style="font-weight: 600; font-size: 0.95rem; color: #d97706;">Test 3: Alerta Warning</div>
                        <div style="font-size: 0.8rem; color: #6b7280;">Usa <code>Notification::make()->warning()->send()</code>.</div>
                    </div>
                    <x-filament::button wire:click="testWarningNotification" color="warning">
                        Lanzar Alerta Warning
                    </x-filament::button>
                </div>

                {{-- Test 4 --}}
                <div style="border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; display: flex; flex-direction: column; justify-content: space-between; gap: 0.75rem;">
                    <div>
                        <div style="font-weight: 600; font-size: 0.95rem; color: #2563eb;">Test 4: Base de Datos (Campana)</div>
                        <div style="font-size: 0.8rem; color: #6b7280;">Usa <code>sendToDatabase(auth()->user())</code>. Debe reflejarse en la campana del topbar.</div>
                    </div>
                    <x-filament::button wire:click="testDatabaseNotification" color="info">
                        Guardar en Campana DB
                    </x-filament::button>
                </div>

                {{-- Test 5 --}}
                <div style="border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; display: flex; flex-direction: column; justify-content: space-between; gap: 0.75rem; background-color: #fdf4ff;">
                    <div>
                        <div style="font-weight: 600; font-size: 0.95rem; color: #9333ea;">Test 5: Livewire Dispatch Directo</div>
                        <div style="font-size: 0.8rem; color: #6b7280;">Dispara <code>$this->dispatch('notificationSent', ...)</code> directamente sin pasar por sesión.</div>
                    </div>
                    <x-filament::button wire:click="testDirectLivewireEvent" color="primary">
                        Probar Dispatch Directo
                    </x-filament::button>
                </div>

                {{-- Test 6 --}}
                <div style="border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; display: flex; flex-direction: column; justify-content: space-between; gap: 0.75rem; background-color: #fdf4ff;">
                    <div>
                        <div style="font-weight: 600; font-size: 0.95rem; color: #9333ea;">Test 6: Manual Session + Dispatch</div>
                        <div style="font-size: 0.8rem; color: #6b7280;">Inyecta en <code>session()->push()</code> y llama <code>$this->dispatch('notificationsSent')</code> explícitamente.</div>
                    </div>
                    <x-filament::button wire:click="testManualSessionPush" color="gray">
                        Probar Session + Dispatch
                    </x-filament::button>
                </div>

                {{-- Test 7 --}}
                <div style="border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; display: flex; flex-direction: column; justify-content: space-between; gap: 0.75rem; background-color: #f0fdfa;">
                    <div>
                        <div style="font-weight: 600; font-size: 0.95rem; color: #0d9488;">Test 7: JavaScript / Alpine (Cliente)</div>
                        <div style="font-size: 0.8rem; color: #6b7280;">Invoca <code>new FilamentNotification().send()</code> en el navegador mediante <code>$this->js()</code>.</div>
                    </div>
                    <x-filament::button wire:click="testJavascriptNotification" color="teal">
                        Disparar desde JavaScript
                    </x-filament::button>
                </div>

                {{-- Test 8 --}}
                <div style="border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; display: flex; flex-direction: column; justify-content: space-between; gap: 0.75rem; background-color: #f0fdfa;">
                    <div>
                        <div style="font-weight: 600; font-size: 0.95rem; color: #0d9488;">Test 8: Native Alert de Navegador</div>
                        <div style="font-size: 0.8rem; color: #6b7280;">Ejecuta <code>window.alert()</code> vía <code>$this->js()</code> para confirmar el canal Livewire-JS.</div>
                    </div>
                    <x-filament::button wire:click="testNativeAlert" color="gray">
                        Probar alert() Nativo
                    </x-filament::button>
                </div>

            </div>
        </div>

        {{-- Diagnóstico en el Cliente (Navegador) --}}
        <div x-data="{
            hasFilamentNotification: typeof window.FilamentNotification !== 'undefined',
            hasAlpine: typeof window.Alpine !== 'undefined',
            hasLivewire: typeof window.Livewire !== 'undefined',
            checkWindow() {
                this.hasFilamentNotification = typeof window.FilamentNotification !== 'undefined';
                this.hasAlpine = typeof window.Alpine !== 'undefined';
                this.hasLivewire = typeof window.Livewire !== 'undefined';
            }
        }" style="background-color: white; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.25rem;">
            <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem; color: #111827;">
                Estado del Cliente (JavaScript en el Navegador)
            </h3>
            <p style="font-size: 0.875rem; color: #6b7280; margin-bottom: 1rem;">
                Variables globales detectadas por Alpine en tu pantalla:
            </p>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap; font-size: 0.875rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.75rem; border-radius: 0.375rem; border: 1px solid #e5e7eb;">
                    <span>Alpine.js:</span>
                    <span x-text="hasAlpine ? '✅ Cargado' : '❌ No detectado'" :style="hasAlpine ? 'color: #16a34a; font-weight: 600;' : 'color: #dc2626; font-weight: 600;'"></span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.75rem; border-radius: 0.375rem; border: 1px solid #e5e7eb;">
                    <span>Livewire:</span>
                    <span x-text="hasLivewire ? '✅ Cargado' : '❌ No detectado'" :style="hasLivewire ? 'color: #16a34a; font-weight: 600;' : 'color: #dc2626; font-weight: 600;'"></span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.75rem; border-radius: 0.375rem; border: 1px solid #e5e7eb;">
                    <span>window.FilamentNotification:</span>
                    <span x-text="hasFilamentNotification ? '✅ Presente' : '❌ Ausente'" :style="hasFilamentNotification ? 'color: #16a34a; font-weight: 600;' : 'color: #dc2626; font-weight: 600;'"></span>
                </div>
            </div>
        </div>

    </div>
</x-filament-panels::page>
