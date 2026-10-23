<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensure the database matches the app's expected schema, even when older
     * migrations were partially applied or the database was created manually.
     */
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasTable('admins')) {
            Schema::rename('users', 'admins');
        }

        if (! Schema::hasTable('toda_organizations')) {
            Schema::create('toda_organizations', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('barangay_travel_area');
                $table->string('address')->nullable();
                $table->string('contact_number', 40)->nullable();
                $table->string('email')->nullable();
                $table->string('status')->default('Active');
                $table->timestamps();
            });
        } else {
            Schema::table('toda_organizations', function (Blueprint $table): void {
                if (! Schema::hasColumn('toda_organizations', 'address')) {
                    $table->string('address')->nullable();
                }
                if (! Schema::hasColumn('toda_organizations', 'contact_number')) {
                    $table->string('contact_number', 40)->nullable();
                }
                if (! Schema::hasColumn('toda_organizations', 'email')) {
                    $table->string('email')->nullable();
                }
                if (! Schema::hasColumn('toda_organizations', 'status')) {
                    $table->string('status')->default('Active');
                }
            });
        }

        if (! Schema::hasTable('admins')) {
            Schema::create('admins', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('role')->default('User');
                $table->string('user_id')->nullable()->unique();
                $table->string('license_number')->nullable();
                $table->string('contact')->nullable();
                $table->text('address')->nullable();
                $table->string('status')->default('Active');
                $table->string('unit')->nullable();
                $table->string('shift_status')->nullable()->default('Available');
                $table->boolean('must_change_password')->default(false);
                $table->string('profile_picture')->nullable();
                $table->foreignId('organization_id')->nullable()->constrained('toda_organizations')->nullOnDelete();
                $table->string('first_name')->nullable();
                $table->string('middle_name')->nullable();
                $table->string('last_name')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        } else {
            Schema::table('admins', function (Blueprint $table): void {
                if (! Schema::hasColumn('admins', 'role')) {
                    $table->string('role')->default('User')->after('name');
                }
                if (! Schema::hasColumn('admins', 'user_id')) {
                    $table->string('user_id')->nullable()->unique()->after('id');
                }
                if (! Schema::hasColumn('admins', 'license_number')) {
                    $table->string('license_number')->nullable()->after('email');
                }
                if (! Schema::hasColumn('admins', 'contact')) {
                    $table->string('contact')->nullable()->after('license_number');
                }
                if (! Schema::hasColumn('admins', 'status')) {
                    $table->string('status')->default('Active')->after('address');
                }
                if (! Schema::hasColumn('admins', 'unit')) {
                    $table->string('unit')->nullable();
                }
                if (! Schema::hasColumn('admins', 'shift_status')) {
                    $table->string('shift_status')->nullable()->default('Available');
                }
                if (! Schema::hasColumn('admins', 'must_change_password')) {
                    $table->boolean('must_change_password')->default(false);
                }
                if (! Schema::hasColumn('admins', 'profile_picture')) {
                    $table->string('profile_picture')->nullable();
                }
                if (! Schema::hasColumn('admins', 'organization_id')) {
                    $table->foreignId('organization_id')->nullable()->constrained('toda_organizations')->nullOnDelete();
                }
                if (! Schema::hasColumn('admins', 'first_name')) {
                    $table->string('first_name')->nullable();
                }
                if (! Schema::hasColumn('admins', 'middle_name')) {
                    $table->string('middle_name')->nullable();
                }
                if (! Schema::hasColumn('admins', 'last_name')) {
                    $table->string('last_name')->nullable();
                }
            });
        }

        if (! Schema::hasTable('drivers')) {
            Schema::create('drivers', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->string('license_number')->nullable();
                $table->string('contact_number')->nullable();
                $table->string('vehicle_number')->nullable();
                $table->string('status')->default('Active');
                $table->string('first_name')->nullable();
                $table->string('middle_name')->nullable();
                $table->string('last_name')->nullable();
                $table->text('address')->nullable();
                $table->date('license_expiration_date')->nullable();
                $table->foreignId('organization_id')->nullable()->constrained('toda_organizations')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamp('rejected_at')->nullable();
                $table->foreignId('rejected_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->text('rejection_reason')->nullable();
                $table->foreignId('registered_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamps();
            });
        } else {
            Schema::table('drivers', function (Blueprint $table): void {
                if (! Schema::hasColumn('drivers', 'name')) {
                    $table->string('name')->nullable()->after('id');
                }
                if (! Schema::hasColumn('drivers', 'license_number')) {
                    $table->string('license_number')->nullable();
                }
                if (! Schema::hasColumn('drivers', 'contact_number')) {
                    $table->string('contact_number')->nullable();
                }
                if (! Schema::hasColumn('drivers', 'vehicle_number')) {
                    $table->string('vehicle_number')->nullable();
                }
                if (! Schema::hasColumn('drivers', 'status')) {
                    $table->string('status')->default('Active');
                }
                if (! Schema::hasColumn('drivers', 'first_name')) {
                    $table->string('first_name')->nullable();
                }
                if (! Schema::hasColumn('drivers', 'middle_name')) {
                    $table->string('middle_name')->nullable();
                }
                if (! Schema::hasColumn('drivers', 'last_name')) {
                    $table->string('last_name')->nullable();
                }
                if (! Schema::hasColumn('drivers', 'address')) {
                    $table->text('address')->nullable();
                }
                if (! Schema::hasColumn('drivers', 'license_expiration_date')) {
                    $table->date('license_expiration_date')->nullable();
                }
                if (! Schema::hasColumn('drivers', 'organization_id')) {
                    $table->foreignId('organization_id')->nullable()->constrained('toda_organizations')->nullOnDelete();
                }
                if (! Schema::hasColumn('drivers', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable();
                }
                if (! Schema::hasColumn('drivers', 'approved_by')) {
                    $table->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
                }
                if (! Schema::hasColumn('drivers', 'rejected_at')) {
                    $table->timestamp('rejected_at')->nullable();
                }
                if (! Schema::hasColumn('drivers', 'rejected_by')) {
                    $table->foreignId('rejected_by')->nullable()->constrained('admins')->nullOnDelete();
                }
                if (! Schema::hasColumn('drivers', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable();
                }
                if (! Schema::hasColumn('drivers', 'registered_by')) {
                    $table->foreignId('registered_by')->nullable()->constrained('admins')->nullOnDelete();
                }
            });
        }

        if (! Schema::hasTable('vehicles')) {
            Schema::create('vehicles', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->string('plate_number')->nullable();
                $table->string('vehicle_type')->nullable();
                $table->string('status')->default('Pending');
                $table->foreignId('organization_id')->nullable()->constrained('toda_organizations')->nullOnDelete();
                $table->timestamps();
            });
        } else {
            Schema::table('vehicles', function (Blueprint $table): void {
                if (! Schema::hasColumn('vehicles', 'name')) {
                    $table->string('name')->nullable();
                }
                if (! Schema::hasColumn('vehicles', 'plate_number')) {
                    $table->string('plate_number')->nullable();
                }
                if (! Schema::hasColumn('vehicles', 'vehicle_type')) {
                    $table->string('vehicle_type')->nullable();
                }
                if (! Schema::hasColumn('vehicles', 'status')) {
                    $table->string('status')->default('Pending');
                }
                if (! Schema::hasColumn('vehicles', 'organization_id')) {
                    $table->foreignId('organization_id')->nullable()->constrained('toda_organizations')->nullOnDelete();
                }
            });
        }

        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('qr_codes')) {
            Schema::create('qr_codes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('driver_id')->unique()->constrained('drivers')->cascadeOnDelete();
                $table->string('qr_token', 32)->unique();
                $table->string('status', 20)->default('Active');
                $table->timestamp('generated_at')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('qr_codes', function (Blueprint $table): void {
                if (! Schema::hasColumn('qr_codes', 'generated_at')) {
                    $table->timestamp('generated_at')->nullable();
                }
            });
        }

        if (! Schema::hasTable('organization_queue_entries')) {
            Schema::create('organization_queue_entries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('organization_id')->constrained('toda_organizations')->cascadeOnDelete();
                $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
                $table->unsignedInteger('queue_number');
                $table->string('status')->default('WAITING');
                $table->foreignId('queued_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->foreignId('served_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamp('served_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->unique(['organization_id', 'queue_number'], 'org_queue_org_number_unique');
                $table->index(['organization_id', 'status', 'queue_number'], 'org_queue_status_number_idx');
            });
        } else {
            Schema::table('organization_queue_entries', function (Blueprint $table): void {
                if (! Schema::hasColumn('organization_queue_entries', 'queue_number')) {
                    $table->unsignedInteger('queue_number');
                }
                if (! Schema::hasColumn('organization_queue_entries', 'status')) {
                    $table->string('status')->default('WAITING');
                }
                if (! Schema::hasColumn('organization_queue_entries', 'queued_by')) {
                    $table->foreignId('queued_by')->nullable()->constrained('admins')->nullOnDelete();
                }
                if (! Schema::hasColumn('organization_queue_entries', 'served_by')) {
                    $table->foreignId('served_by')->nullable()->constrained('admins')->nullOnDelete();
                }
                if (! Schema::hasColumn('organization_queue_entries', 'served_at')) {
                    $table->timestamp('served_at')->nullable();
                }
                if (! Schema::hasColumn('organization_queue_entries', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable();
                }
                if (! Schema::hasIndex('organization_queue_entries', ['organization_id', 'queue_number'], 'unique')) {
                    $table->unique(['organization_id', 'queue_number'], 'org_queue_org_number_unique');
                }
                if (! Schema::hasIndex('organization_queue_entries', ['organization_id', 'status', 'queue_number'])) {
                    $table->index(['organization_id', 'status', 'queue_number'], 'org_queue_status_number_idx');
                }
            });
        }

        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table): void {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }

        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table): void {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Intentionally left empty because this is a schema repair migration.
        // Reverting it would be destructive and not safe for a partially migrated DB.
    }
};
