# 📊 Cálculo de Pérdida de Fertilizantes — Grupo Control

## Descripción General

El **Grupo Control** representa la **verdad de campo** en el proyecto AgroLixiSync. Su función es establecer la referencia real de lixiviación mediante mediciones de conductividad eléctrica (CE) en diferentes profundidades del suelo.

---

## Componentes del Cálculo

### 1. **Pérdida de Fertilizantes (%)**

La **Pérdida %** es el indicador principal que refleja qué porcentaje de fertilizantes se han lixiviado fuera de la zona radicular.

```
Rango de Pérdida: 60% - 86%
```

**Interpretación:**
- **60% - 75%**: Pérdida baja a moderada (tolerable)
- **76% - 100%**: Pérdida alta (crítica, requiere intervención)

---

### 2. **Conductividad Eléctrica (CE)**

Se miden dos niveles de profundidad:

#### **CE Superficial (CE Sup)**
- **Valor base**: 0.420 dS/m
- **Ubicación**: Zona radicular superior (0-20 cm)
- **Significado**: Concentración de sales en donde la planta absorbe agua y nutrientes

#### **CE Profunda (CE Prof)**
- **Fórmula**: `CE Prof = CE Sup × ILx`
- **Ubicación**: Zona de lixiviación profunda (> 20 cm)
- **Significado**: Acumulación de sales que han sido transportadas por debajo de la zona radicular

---

### 3. **Índice de Lixiviación (ILx)**

El **ILx** es un multiplicador que relaciona la CE superficial con la profunda, considerando la magnitud de la pérdida de fertilizantes.

#### **Fórmula**:
```
ILx = 1.05 + (Pérdida % ÷ 400)
```

#### **Cálculo paso a paso**:

**Ejemplo con Pérdida = 60%:**
```
ILx = 1.05 + (60 ÷ 400)
ILx = 1.05 + 0.15
ILx = 1.20
```

**Ejemplo con Pérdida = 80%:**
```
ILx = 1.05 + (80 ÷ 400)
ILx = 1.05 + 0.20
ILx = 1.25
```

#### **Rango de ILx**:
| Pérdida % | ILx Mínimo | ILx Máximo |
|-----------|-----------|-----------|
| 60% | 1.20 | - |
| 80% | 1.25 | - |
| 100% | 1.30 | - |

---

### 4. **CE Profunda Calculada**

Usando la fórmula anterior:

```
CE Prof = CE Sup × ILx = 0.420 × ILx
```

**Ejemplos**:
- **Con Pérdida 60%**: CE Prof = 0.420 × 1.20 = 0.504 dS/m
- **Con Pérdida 80%**: CE Prof = 0.420 × 1.25 = 0.525 dS/m

---

### 5. **Estado del Cultivo**

Se clasifica en dos categorías:

| Estado | Condición | Pérdida % | Acción Recomendada |
|--------|-----------|-----------|-------------------|
| **Baja Pérdida** | Tolerable | ≤ 75% | Monitoreo continuo |
| **Alta Pérdida** | Crítica | > 75% | Ajuste de riego/fertilización inmediato |

---

## Ejemplo Completo de Cálculo

### Datos Iniciales:
- Pérdida de Fertilizantes: **72%**
- CE Superficial: **0.420 dS/m**

### Paso 1: Calcular ILx
```
ILx = 1.05 + (72 ÷ 400)
ILx = 1.05 + 0.18
ILx = 1.23
```

### Paso 2: Calcular CE Profunda
```
CE Prof = 0.420 × 1.23
CE Prof = 0.5166 dS/m
```

### Paso 3: Determinar Estado
```
Como 72% ≤ 75% → Estado = "Baja pérdida"
```

### Resultado Final en la Tabla:
| Planta | Fecha | CE Sup | CE Prof | ILx | Pérdida % | Eventos | Estado |
|--------|-------|--------|---------|-----|-----------|---------|--------|
| P1 | 2026-05-20 | 0.420 | 0.5166 | 1.23 | 72% | 12 | Baja pérdida |

---

## Coherencia del Modelo

### Relaciones Clave:

1. **ILx ↑ cuando Pérdida % ↑**: Más fertilizante lixiviado = mayor multiplicador
2. **CE Prof ↑ cuando ILx ↑**: Más lixiviación = más sales en profundidad
3. **Estado crítico**: Cuando Pérdida % > 75% → Intervención urgente

### Validación:
- Todos los valores de CE Prof deben ser **mayores** que CE Sup
- ILx debe estar en el rango **[1.15 - 1.30]** para pérdidas realistas
- Eventos correlacionan con magnitud de pérdida

---

## Secuencia de Pérdida Típica (15 días)

```
Día 1:  60% ✓ (Línea base)
Día 2:  65% → Aumento leve
Día 3:  72% → Aumento moderado
Día 4:  69% → Fluctuación
Día 5:  74% → Aproximándose al crítico
Día 6:  81% ⚠️ (CRÍTICO)
Día 7:  75% → En límite
Día 8:  80% ⚠️ (CRÍTICO)
Día 9:  73% → Recuperación parcial
Día 10: 66% → Mejora
Día 11: 83% ⚠️ (CRÍTICO)
Día 12: 64% → Buena estabilidad
Día 13: 84% ⚠️ (CRÍTICO)
Día 14: 86% ⚠️ (CRÍTICO - MÁXIMO)
Día 15: 82% ⚠️ (CRÍTICO)
```

---

## Referencias

- **CE (Conductividad Eléctrica)**: Medida de salinidad del suelo (dS/m)
- **Lixiviación**: Movimiento de nutrientes disueltos más allá de la zona radicular
- **ILx**: Índice de Lixiviación, factor de correlación entre profundidades
- **Verdad de Campo**: Mediciones reales de referencia (Grupo Control)

---

**Última actualización**: 2026-06-04  
**Versión**: 1.0  
**Sistema**: AgroLixiSync
