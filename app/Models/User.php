<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;



use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;
    use HasRoles, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'sede_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    // 3. Relación: Un usuario pertenece a una Sede
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    // 4. Configuración obligatoria del Activitylog
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()          // Registra cualquier cambio en los campos definidos en $fillable
            ->logOnlyDirty()         // Solo crea un registro en la BD si los datos realmente cambiaron
            ->dontSubmitEmptyLogs(); // Evita guardar registros vacíos si se da clic en "Guardar" sin alterar nada
    }
    // 5. Permiso para entrar al panel de Filament
    public function canAccessPanel(Panel $panel): bool
    {
        // Antes solo permitía a 'super_admin'. Al devolver true, 
        // Filament permite el login, y luego Filament Shield y las 
        // Policies se encargan de restringir qué puede ver o hacer cada usuario.
        return true; 
        
        // Si necesitas ser estricto sobre quién entra al panel:
        // return $this->hasAnyRole(['super_admin', 'Geólogo', 'Operador']);
    }
}
