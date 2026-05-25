<div class="pf-ficha-container">
    <form method="POST" action="{{ route('pf.ficha.store') }}">
        @csrf
        @php
            $isControl = (isset($location) && $location->experimental_group === 'control');
            $groupLabel = $isControl ? 'Grupo Control (Manual)' : 'Grupo Experimental (Sensores)';
            $groupClass = $isControl ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700';
        @endphp

        @if(isset($location))
            <div class="mb-6 inline-flex items-center gap-2 px-4 py-2 rounded-2xl {{ $groupClass }} text-[10px] font-black uppercase tracking-widest border border-current shadow-sm">
                <i class="fas {{ $isControl ? 'fa-user-edit' : 'fa-robot' }}"></i>
                {{ $groupLabel }}
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 items-end mb-8">
            <div class="lg:col-span-1">
                <label class="section-label mb-2">Ubicación</label>
                <select name="location_id" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-700 text-xs focus:ring-2 focus:ring-emerald-500 outline-none transition-all" onchange="this.form.method='GET'; this.form.action=''; this.form.submit();">
                    <option value="">Seleccionar...</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ (isset($location_id) && $location_id == $loc->id) ? 'selected' : '' }}>
                            {{ $loc->name }} ({{ $loc->experimental_group === 'control' ? 'CTRL' : 'EXP' }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="section-label mb-2">Fecha</label>
                <input type="datetime-local" name="recorded_at" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-700 text-xs outline-none focus:border-emerald-500" value="{{ old('recorded_at', now()->format('Y-m-d\TH:i')) }}" required />
            </div>
            
            <div>
                <label class="section-label mb-2">CE Sup (20cm)</label>
                <input type="number" step="0.001" name="ce_superficial" id="ce_sup_input" 
                       class="w-full p-2.5 {{ !$isControl ? 'bg-slate-100' : 'bg-white border-amber-200 focus:border-amber-500' }} border border-slate-200 rounded-xl font-mono font-bold text-slate-700 text-xs outline-none transition-all" 
                       value="{{ old('ce_superficial', $ce_sup ?? '') }}" 
                       {{ !$isControl ? 'readonly' : '' }} required placeholder="Ingresar CE..." />
            </div>

            <div>
                <label class="section-label mb-2">CE Prof (60cm)</label>
                <input type="number" step="0.001" name="ce_profunda" id="ce_prof_input" 
                       class="w-full p-2.5 {{ !$isControl ? 'bg-slate-100' : 'bg-white border-amber-200 focus:border-amber-500' }} border border-slate-200 rounded-xl font-mono font-bold text-slate-700 text-xs outline-none transition-all" 
                       value="{{ old('ce_profunda', $ce_prof ?? '') }}" 
                       {{ !$isControl ? 'readonly' : '' }} required placeholder="Ingresar CE..." />
            </div>

            <input type="hidden" name="ce_measured" id="ilx_experimental" value="{{ (isset($ce_sup) && $ce_sup > 0 && isset($ce_prof)) ? $ce_prof / $ce_sup : '' }}">

            <div>
                <label class="section-label mb-2 text-indigo-600">Índice Lixiviación (Control)</label>
                <input type="number" step="0.0001" name="ce_reference" id="ilx_control" class="w-full p-2.5 bg-indigo-50 border-2 border-indigo-100 rounded-xl font-mono font-bold text-indigo-700 text-xs outline-none focus:border-indigo-500" placeholder="Referencia" required />
            </div>

            <div>
                <button type="submit" class="w-full py-3 bg-emerald-600 text-white rounded-xl font-black text-[10px] uppercase tracking-wider shadow-lg shadow-emerald-100 hover:bg-emerald-700 transition-all flex items-center justify-center gap-2">
                    <i class="fas fa-save"></i> GUARDAR
                </button>
            </div>
        </div>
        
        <div class="mb-10 p-4 bg-slate-900 rounded-2xl border border-slate-800 flex flex-col md:flex-row justify-between items-center gap-4 shadow-xl">
            <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                <span class="w-6 h-6 bg-slate-800 rounded-full flex items-center justify-center text-emerald-500">
                    <i class="fas fa-calculator text-[10px]"></i>
                </span>
                Fórmula Tesis: <span class="text-slate-200 font-mono">((Índice_CTRL - Índice_EXP) / Índice_CTRL) × 100</span>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-right">
                    <div class="text-[9px] font-black text-slate-500 uppercase">Resultado de Pérdida (PF)</div>
                    <div id="pf_preview_inline" class="text-2xl font-black text-emerald-400 font-mono">--</div>
                </div>
            </div>
        </div>

        @if($errors->any())
            <div class="mb-6 p-4 bg-red-50 border border-red-100 rounded-xl text-red-600 text-xs font-bold">{{ $errors->first() }}</div>
        @endif
        @if(session('success'))
            <div class="mb-6 p-4 bg-emerald-50 border border-emerald-100 rounded-xl text-emerald-600 text-xs font-bold">{{ session('success') }}</div>
        @endif
    </form>

    <div class="overflow-x-auto rounded-2xl border border-slate-100 shadow-sm">
        <table class="w-full text-left">
            <thead class="bg-slate-50/50">
                <tr class="text-slate-400 font-black uppercase tracking-widest text-[9px] border-b border-slate-100">
                    <th class="py-4 px-6">Fecha de Toma</th>
                    <th class="py-4 px-4">CE Sup</th>
                    <th class="py-4 px-4">CE Prof</th>
                    <th class="py-4 px-4">Índice Exp</th>
                    <th class="py-4 px-4">Índice Ctrl</th>
                    <th class="py-4 px-6 text-right">% Pérdida</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($records as $r)
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="py-4 px-6 font-bold text-slate-700 text-xs">{{ $r->recorded_at ? $r->recorded_at->format('d/m/Y H:i') : '' }}</td>
                        <td class="py-4 px-4 font-mono text-xs">{{ number_format($r->ce_superficial, 3) }}</td>
                        <td class="py-4 px-4 font-mono text-xs">{{ number_format($r->ce_profunda, 3) }}</td>
                        <td class="py-4 px-4 font-mono font-black text-indigo-600 text-xs">{{ number_format($r->ce_measured, 4) }}</td>
                        <td class="py-4 px-4 font-mono text-emerald-600 text-xs">{{ number_format($r->ce_reference, 4) }}</td>
                        <td class="py-4 px-6 text-right">
                            <span class="px-3 py-1 rounded-full {{ $r->pf_percentage > 10 ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-600' }} font-black text-xs">
                                {{ $r->pf_percentage !== null ? number_format($r->pf_percentage, 2) . '%' : '--' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-16 text-center text-slate-300 font-bold italic">No hay registros PF guardados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
function computePFInline() {
    const ce_sup = parseFloat(document.getElementById('ce_sup_input').value) || 0;
    const ce_prof = parseFloat(document.getElementById('ce_prof_input').value) || 0;
    
    let experimental = 0;
    if (ce_sup > 0) {
        experimental = ce_prof / ce_sup;
        document.getElementById('ilx_experimental').value = experimental.toFixed(4);
    }

    const control = parseFloat(document.getElementById('ilx_control').value) || 0;
    const pfEl = document.getElementById('pf_preview_inline');
    
    if (control === 0) { 
        pfEl.textContent = '--'; 
        return; 
    }
    
    const pf = ((control - experimental) / control) * 100;
    pfEl.textContent = pf.toFixed(2) + '%';
    
    if (pf > 15) pfEl.className = 'text-2xl font-black text-red-400 font-mono';
    else if (pf < 0) pfEl.className = 'text-2xl font-black text-blue-400 font-mono';
    else pfEl.className = 'text-2xl font-black text-emerald-400 font-mono';
}

const ilxControlEl = document.getElementById('ilx_control');
const ceSupEl = document.getElementById('ce_sup_input');
const ceProfEl = document.getElementById('ce_prof_input');

if (ilxControlEl) ilxControlEl.addEventListener('input', computePFInline);
if (ceSupEl) ceSupEl.addEventListener('input', computePFInline);
if (ceProfEl) ceProfEl.addEventListener('input', computePFInline);

computePFInline();
</script>
