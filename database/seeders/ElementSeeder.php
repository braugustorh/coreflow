<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ElementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $elements = [
            ['symbol' => 'Ag', 'name' => 'Silver'],
            ['symbol' => 'Al', 'name' => 'Aluminium'],
            ['symbol' => 'As', 'name' => 'Arsenic'],
            ['symbol' => 'Au', 'name' => 'Gold'],
            ['symbol' => 'B', 'name' => 'Boron'],
            ['symbol' => 'Ba', 'name' => 'Barium'],
            ['symbol' => 'Be', 'name' => 'Beryllium'],
            ['symbol' => 'Bi', 'name' => 'Bismuth'],
            ['symbol' => 'Br', 'name' => 'Bromide'],
            ['symbol' => 'C', 'name' => 'Carbon'],
            ['symbol' => 'Ca', 'name' => 'Calcium'],
            ['symbol' => 'Cd', 'name' => 'Cadmium'],
            ['symbol' => 'Ce', 'name' => 'Cerium - Lanthanide Series'],
            ['symbol' => 'Cl', 'name' => 'Chloride'],
            ['symbol' => 'Co', 'name' => 'Cobalt'],
            ['symbol' => 'Cr', 'name' => 'Chromium'],
            ['symbol' => 'Cs', 'name' => 'Cesium'],
            ['symbol' => 'Cu', 'name' => 'Copper'],
            ['symbol' => 'Dy', 'name' => 'Dysprosium - Lanthanide Series'],
            ['symbol' => 'Er', 'name' => 'Erbium - Lanthanide Series'],
            ['symbol' => 'Eu', 'name' => 'Europium - Lanthanide Series'],
            ['symbol' => 'F', 'name' => 'Fluoride'],
            ['symbol' => 'Fe', 'name' => 'Iron'],
            ['symbol' => 'Fr', 'name' => 'Francium'],
            ['symbol' => 'Ga', 'name' => 'Gallium'],
            ['symbol' => 'Gd', 'name' => 'Gadolinium - Lanthanide Series'],
            ['symbol' => 'Ge', 'name' => 'Germanium'],
            ['symbol' => 'Hf', 'name' => 'Hafnium'],
            ['symbol' => 'Hg', 'name' => 'Mercury'],
            ['symbol' => 'Ho', 'name' => 'Holmium - Lanthanide Series'],
            ['symbol' => 'Ind', 'name' => 'Indium'],
            ['symbol' => 'Iod', 'name' => 'Iodide'],
            ['symbol' => 'Ir', 'name' => 'Iridium'],
            ['symbol' => 'K', 'name' => 'Potassium'],
            ['symbol' => 'La', 'name' => 'Lanthanum - Lanthanide Series'],
            ['symbol' => 'Li', 'name' => 'Lithium'],
            ['symbol' => 'Lu', 'name' => 'Lutetium - Lanthanide Series'],
            ['symbol' => 'Mg', 'name' => 'Magnesium'],
            ['symbol' => 'Mn', 'name' => 'Manganese'],
            ['symbol' => 'Mo', 'name' => 'Molybdenum'],
            ['symbol' => 'Na', 'name' => 'Sodium'],
            ['symbol' => 'Nb', 'name' => 'Niobium'],
            ['symbol' => 'Nd', 'name' => 'Neodymium - Lanthanide Series'],
            ['symbol' => 'Ni', 'name' => 'Nickel'],
            ['symbol' => 'Os', 'name' => 'Osmium'],
            ['symbol' => 'P', 'name' => 'Phosphorus'],
            ['symbol' => 'Pb', 'name' => 'Lead'],
            ['symbol' => 'Pd', 'name' => 'Palladium'],
            ['symbol' => 'Pm', 'name' => 'Promethium - Lanthanide Series'],
            ['symbol' => 'Po', 'name' => 'Polonium'],
            ['symbol' => 'Pr', 'name' => 'Praseodymium - Lanthanide Series'],
            ['symbol' => 'Pt', 'name' => 'Platinum'],
            ['symbol' => 'Ra', 'name' => 'Radium'],
            ['symbol' => 'Rb', 'name' => 'Rubidium'],
            ['symbol' => 'Re', 'name' => 'Rhenium'],
            ['symbol' => 'Rh', 'name' => 'Rhodium'],
            ['symbol' => 'Ru', 'name' => 'Ruthenium'],
            ['symbol' => 'S', 'name' => 'Sulphur'],
            ['symbol' => 'Sb', 'name' => 'Antimony'],
            ['symbol' => 'Sc', 'name' => 'Scandium'],
            ['symbol' => 'Se', 'name' => 'Selenium'],
            ['symbol' => 'SG', 'name' => 'SG'],
            ['symbol' => 'Si', 'name' => 'Silicon'],
            ['symbol' => 'Sm', 'name' => 'Samarium - Lanthanide Series'],
            ['symbol' => 'Sn', 'name' => 'Tin'],
            ['symbol' => 'Sr', 'name' => 'Strontium'],
            ['symbol' => 'Ta', 'name' => 'Tantalum'],
            ['symbol' => 'Tb', 'name' => 'Terbium - Lanthanide Series'],
            ['symbol' => 'Tc', 'name' => 'Technetium'],
            ['symbol' => 'Te', 'name' => 'Tellurium'],
            ['symbol' => 'Th', 'name' => 'Thorium'],
            ['symbol' => 'Ti', 'name' => 'Titanium'],
            ['symbol' => 'Tl', 'name' => 'Thallium'],
            ['symbol' => 'Tm', 'name' => 'Thulium - Lanthanide Series'],
            ['symbol' => 'U', 'name' => 'Uranium'],
            ['symbol' => 'V', 'name' => 'Vanadium'],
            ['symbol' => 'W', 'name' => 'Tungsten'],
            ['symbol' => 'Y', 'name' => 'Yttrium'],
            ['symbol' => 'Yb', 'name' => 'Ytterbium - Lanthanide Series'],
            ['symbol' => 'Zn', 'name' => 'Zinc'],
            ['symbol' => 'Zr', 'name' => 'Zirconium'],
        ];

        foreach ($elements as $el) {
            \App\Models\Element::updateOrCreate(['symbol' => $el['symbol']], ['name' => $el['name']]);
        }
    }
}
