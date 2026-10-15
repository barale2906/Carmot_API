<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\Asistencia;
use App\Models\Academico\NotaEstudiante;
use App\Models\Financiero\ReciboPago\ReciboPago;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Un usuario con solo el rol alumno únicamente consulta su propia información
 * académica y financiera (bug B44 / F13). El personal con los mismos permisos ve a todos.
 */
class AlumnoSoloVeLoPropioTest extends TestCase
{
    use RefreshDatabase;

    private User $alumno;
    private User $otroAlumno;
    private User $profesor;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permisos = ['aca_notas', 'aca_asistencias', 'fin_reciboPagoPDF'];
        foreach ($permisos as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }
        Role::create(['name' => User::ROL_ALUMNO])->givePermissionTo($permisos);
        Role::create(['name' => 'profesor'])->givePermissionTo($permisos);

        $this->alumno = User::factory()->create();
        $this->alumno->assignRole(User::ROL_ALUMNO);
        $this->otroAlumno = User::factory()->create();
        $this->otroAlumno->assignRole(User::ROL_ALUMNO);
        $this->profesor = User::factory()->create();
        $this->profesor->assignRole('profesor');
    }

    /** @test */
    public function el_alumno_solo_lista_sus_propias_notas(): void
    {
        $propia = NotaEstudiante::factory()->create(['estudiante_id' => $this->alumno->id]);
        NotaEstudiante::factory()->create(['estudiante_id' => $this->otroAlumno->id]);

        $this->actingAs($this->alumno, 'sanctum')
            ->getJson('/api/academico/notas-estudiantes?estudiante_id=' . $this->otroAlumno->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $propia->id);
    }

    /** @test */
    public function el_personal_con_el_mismo_permiso_lista_todas_las_notas(): void
    {
        NotaEstudiante::factory()->create(['estudiante_id' => $this->alumno->id]);
        NotaEstudiante::factory()->create(['estudiante_id' => $this->otroAlumno->id]);

        $this->actingAs($this->profesor, 'sanctum')
            ->getJson('/api/academico/notas-estudiantes')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function el_alumno_no_ve_la_nota_de_otro(): void
    {
        $ajena = NotaEstudiante::factory()->create(['estudiante_id' => $this->otroAlumno->id]);
        $propia = NotaEstudiante::factory()->create(['estudiante_id' => $this->alumno->id]);

        $this->actingAs($this->alumno, 'sanctum')
            ->getJson("/api/academico/notas-estudiantes/{$ajena->id}")
            ->assertForbidden();

        $this->actingAs($this->alumno, 'sanctum')
            ->getJson("/api/academico/notas-estudiantes/{$propia->id}")
            ->assertOk();
    }

    /** @test */
    public function el_alumno_no_ve_la_sabana_ni_la_nota_final_de_otro(): void
    {
        $otro = $this->otroAlumno->id;

        $this->actingAs($this->alumno, 'sanctum')
            ->getJson("/api/academico/notas-estudiantes/estudiante/{$otro}/sabana")
            ->assertForbidden();

        $this->actingAs($this->alumno, 'sanctum')
            ->getJson("/api/academico/notas-estudiantes/estudiante/{$otro}/modulo/1/nota-final")
            ->assertForbidden();
    }

    /** @test */
    public function el_alumno_no_consulta_vistas_de_grupo_completo(): void
    {
        $this->actingAs($this->alumno, 'sanctum')
            ->getJson('/api/academico/notas-estudiantes/grupo/1/modulo/1/sabana')
            ->assertForbidden();

        $this->actingAs($this->alumno, 'sanctum')
            ->getJson('/api/academico/asistencias/lista-asistencia/1')
            ->assertForbidden();

        $this->actingAs($this->alumno, 'sanctum')
            ->getJson('/api/academico/asistencias/reporte/grupo/1')
            ->assertForbidden();
    }

    /** @test */
    public function el_alumno_solo_ve_sus_propias_asistencias(): void
    {
        $propia = Asistencia::factory()->create(['estudiante_id' => $this->alumno->id]);
        $ajena = Asistencia::factory()->create(['estudiante_id' => $this->otroAlumno->id]);

        $this->actingAs($this->alumno, 'sanctum')
            ->getJson('/api/academico/asistencias')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $propia->id);

        $this->actingAs($this->alumno, 'sanctum')
            ->getJson("/api/academico/asistencias/{$ajena->id}")
            ->assertForbidden();

        $this->actingAs($this->alumno, 'sanctum')
            ->getJson("/api/academico/asistencias/reporte/estudiante/{$this->otroAlumno->id}")
            ->assertForbidden();

        $this->actingAs($this->alumno, 'sanctum')
            ->getJson("/api/academico/asistencias/reporte/estudiante/{$this->alumno->id}")
            ->assertOk();
    }

    /** @test */
    public function el_alumno_no_descarga_el_recibo_de_otro(): void
    {
        $recibo = ReciboPago::factory()->academico()->create(['estudiante_id' => $this->otroAlumno->id]);

        $this->actingAs($this->alumno, 'sanctum')
            ->get("/api/financiero/recibos-pago/{$recibo->id}/pdf")
            ->assertForbidden();
    }
}
