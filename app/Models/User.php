<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class User extends Authenticatable implements HasMedia
{
    use HasApiTokens;
    use HasRoles;
    use InteractsWithMedia;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name', // Legacy (Nombre completo concatenado)
        'first_name',
        'paternal_surname',
        'maternal_surname',
        'email',
        'password',
        'branch_id',
        'is_active',
        'phone', // Whatsapp
        
        // Datos Legales
        'birth_date',
        'curp',
        'rfc',
        'nss',

        // Domicilio
        'street',
        'exterior_number',
        'interior_number',
        'neighborhood',
        'zip_code',
        'municipality',
        'state',
        'address_references',
        'cross_streets',

        // Datos Bancarios
        'bank_account_holder',
        'bank_name',
        'bank_clabe',
        'bank_account_number',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
        'full_name_label', // Opcional: para usar en frontend si se desea
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
            'birth_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    // --- Accessors ---

    public function getFullNameLabelAttribute(): string
    {
        return trim("{$this->first_name} {$this->paternal_surname} {$this->maternal_surname}");
    }

    // --- Relaciones ---

    /**
     * Relación con la Sucursal (Branch).
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Relación con Beneficiarios (1 a Muchos).
     */
    public function beneficiaries(): HasMany
    {
        return $this->hasMany(Beneficiary::class);
    }

    /**
     * Relación Polimórfica para Contactos de Emergencia.
     */
    public function contacts(): MorphMany
    {
        return $this->morphMany(Contact::class, 'contactable');
    }

    // Tareas asignadas AL usuario
    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    // Tareas creadas POR el usuario
    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    // Ventas realizadas
    public function sales(): HasMany
    {
        return $this->hasMany(ServiceOrder::class, 'sales_rep_id');
    }

    // Instalaciones/Mantenimientos asignados como técnico
    public function technicalServices(): HasMany
    {
        return $this->hasMany(ServiceOrder::class, 'technician_id');
    }

    public function tasks()
    {
        return $this->belongsToMany(Task::class, 'task_user');
    }
}