<?php

namespace App\Observers;

use App\Models\DrillHole;

class DrillHoleObserver
{
    /**
     * Campos correspondientes a coordenadas reales y levantamiento topográfico del collar.
     */
    protected array $installedFields = [
        'easting',
        'northing',
        'elevation',
        'dip',
        'azimuth',
        'survey_pdf_path',
        'planilla',
        'survey_responsible',
        'survey_date',
    ];

    /**
     * Campos correspondientes a diseño / planeados.
     */
    protected array $plannedFields = [
        'planned_easting',
        'planned_northing',
        'planned_elevation',
        'planned_dip',
        'planned_azimuth',
    ];

    /**
     * Se ejecuta antes de guardar el barreno (creación o actualización).
     */
    public function saving(DrillHole $drillHole): void
    {
        // 1. Normalizar consistencia de jerarquía
        if (!empty($drillHole->parent_id)) {
            $drillHole->is_child = true;
            // 2. Si el barreno hijo carece de atributos instalados, heredarlos de la madre
            $this->inheritFromParentIfMissing($drillHole);
        } elseif (!$drillHole->is_child) {
            $drillHole->parent_id = null;
        }
    }

    /**
     * Se ejecuta después de actualizar el barreno.
     */
    public function updated(DrillHole $drillHole): void
    {
        // Si el barreno actualizado tiene barrenos hijos, propagar atributos
        $this->cascadeInstalledAttributesToChildren($drillHole);
    }

    /**
     * Hereda atributos de la madre si el barreno hijo los tiene vacíos al guardarse.
     */
    protected function inheritFromParentIfMissing(DrillHole $child): void
    {
        $parent = $child->parent ?? DrillHole::find($child->parent_id);

        if (!$parent) {
            return;
        }

        // Atributos Instalados y Reporte Topográfico
        if ($child->easting === null && $parent->easting !== null) $child->easting = $parent->easting;
        if ($child->northing === null && $parent->northing !== null) $child->northing = $parent->northing;
        if ($child->elevation === null && $parent->elevation !== null) $child->elevation = $parent->elevation;
        if ($child->dip === null && $parent->dip !== null) $child->dip = $parent->dip;
        if ($child->azimuth === null && $parent->azimuth !== null) $child->azimuth = $parent->azimuth;

        if (empty($child->survey_pdf_path) && !empty($parent->survey_pdf_path)) $child->survey_pdf_path = $parent->survey_pdf_path;
        if (empty($child->planilla) && !empty($parent->planilla)) $child->planilla = $parent->planilla;
        if (empty($child->survey_responsible) && !empty($parent->survey_responsible)) $child->survey_responsible = $parent->survey_responsible;
        if (empty($child->survey_date) && !empty($parent->survey_date)) $child->survey_date = $parent->survey_date;

        // Atributos Planeados
        if ($child->planned_easting === null && $parent->planned_easting !== null) $child->planned_easting = $parent->planned_easting;
        if ($child->planned_northing === null && $parent->planned_northing !== null) $child->planned_northing = $parent->planned_northing;
        if ($child->planned_elevation === null && $parent->planned_elevation !== null) $child->planned_elevation = $parent->planned_elevation;
        if ($child->planned_dip === null && $parent->planned_dip !== null) $child->planned_dip = $parent->planned_dip;
        if ($child->planned_azimuth === null && $parent->planned_azimuth !== null) $child->planned_azimuth = $parent->planned_azimuth;

        // Categorización y Ubicación
        if (empty($child->sede_id) && !empty($parent->sede_id)) $child->sede_id = $parent->sede_id;
        if (empty($child->proyecto_id) && !empty($parent->proyecto_id)) $child->proyecto_id = $parent->proyecto_id;
        if (empty($child->target) && !empty($parent->target)) $child->target = $parent->target;
        if (empty($child->drilling_type) && !empty($parent->drilling_type)) $child->drilling_type = $parent->drilling_type;
    }

    /**
     * Propaga en cascada los datos instalados y reporte topográfico a los barrenos hijos.
     */
    public function cascadeInstalledAttributesToChildren(DrillHole $parent): void
    {
        $children = $parent->children()->get();

        if ($children->isEmpty()) {
            return;
        }

        foreach ($children as $child) {
            $needsUpdate = false;

            // 1. Atributos instalados numéricos (easting, northing, elevation, dip, azimuth)
            $numericInstalled = ['easting', 'northing', 'elevation', 'dip', 'azimuth'];
            foreach ($numericInstalled as $field) {
                if ($parent->{$field} !== null) {
                    if ($child->{$field} === null || abs((float)$child->{$field} - (float)$parent->{$field}) > 0.0001) {
                        $child->{$field} = $parent->{$field};
                        $needsUpdate = true;
                    }
                }
            }

            // 2. Metadatos del Reporte Topográfico (survey_pdf_path, planilla, survey_responsible)
            $stringFields = ['survey_pdf_path', 'planilla', 'survey_responsible'];
            foreach ($stringFields as $field) {
                if (!empty($parent->{$field}) && trim((string)$child->{$field}) !== trim((string)$parent->{$field})) {
                    $child->{$field} = $parent->{$field};
                    $needsUpdate = true;
                }
            }

            // 3. Fecha de levantamiento
            if (!empty($parent->survey_date)) {
                $parentDate = $parent->survey_date instanceof \DateTimeInterface
                    ? $parent->survey_date->format('Y-m-d')
                    : (string)$parent->survey_date;
                $childDate = $child->survey_date instanceof \DateTimeInterface
                    ? $child->survey_date->format('Y-m-d')
                    : (string)$child->survey_date;

                if ($parentDate !== $childDate) {
                    $child->survey_date = $parentDate;
                    $needsUpdate = true;
                }
            }

            // 4. Atributos planeados (solo si el hijo no los tiene definidos)
            foreach ($this->plannedFields as $field) {
                if ($parent->{$field} !== null && $child->{$field} === null) {
                    $child->{$field} = $parent->{$field};
                    $needsUpdate = true;
                }
            }

            // 5. Ubicación y proyecto (solo si el hijo no los tiene definidos)
            if (!empty($parent->sede_id) && empty($child->sede_id)) {
                $child->sede_id = $parent->sede_id;
                $needsUpdate = true;
            }
            if (!empty($parent->proyecto_id) && empty($child->proyecto_id)) {
                $child->proyecto_id = $parent->proyecto_id;
                $needsUpdate = true;
            }
            if (!empty($parent->target) && empty($child->target)) {
                $child->target = $parent->target;
                $needsUpdate = true;
            }
            if (!empty($parent->drilling_type) && empty($child->drilling_type)) {
                $child->drilling_type = $parent->drilling_type;
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                // Guardar silenciosamente para evitar bucles infinitos de eventos
                $child->saveQuietly();

                // Propagación recursiva por si este hijo tiene a su vez sub-hijos (wedges de wedges)
                $this->cascadeInstalledAttributesToChildren($child);
            }
        }
    }
}
