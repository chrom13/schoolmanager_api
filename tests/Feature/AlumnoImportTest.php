<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\CicloEscolar;
use App\Models\Escuela;
use App\Models\Grado;
use App\Models\Grupo;
use App\Models\Nivel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AlumnoImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Escuela $escuela;
    private CicloEscolar $ciclo;
    private Grado $grado;
    private Grupo $grupo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->escuela = Escuela::create([
            'nombre'                 => 'Escuela Test',
            'slug'                   => 'escuela-test',
            'email'                  => 'test@escuela.com',
            'onboarding_completado'  => true,
        ]);

        $this->user = User::create([
            'escuela_id'        => $this->escuela->id,
            'name'              => 'Admin Test',
            'email'             => 'admin@escuela.com',
            'password'          => bcrypt('password'),
            'rol'               => 'admin',
            'email_verified_at' => now(),
        ]);

        $nivel = Nivel::create([
            'escuela_id' => $this->escuela->id,
            'nombre'     => 'primaria',
        ]);

        $this->grado = Grado::create([
            'escuela_id' => $this->escuela->id,
            'nivel_id'   => $nivel->id,
            'nombre'     => '1er Grado',
            'orden'      => 1,
        ]);

        $this->grupo = Grupo::create([
            'escuela_id'      => $this->escuela->id,
            'grado_id'        => $this->grado->id,
            'nombre'          => 'A',
            'capacidad_maxima' => 30,
        ]);

        $this->ciclo = CicloEscolar::create([
            'escuela_id'  => $this->escuela->id,
            'nivel_id'    => $nivel->id,
            'nombre'      => '2024-2025',
            'fecha_inicio' => '2024-08-01',
            'fecha_fin'   => '2025-06-30',
            'activo'      => true,
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makeCsv(array $rows): UploadedFile
    {
        $headers = ['nombre', 'apellido_paterno', 'apellido_materno', 'curp', 'fecha_nacimiento', 'grado_nombre', 'grupo_nombre', 'padre_nombre', 'padre_email', 'padre_telefono'];

        $content = implode(',', $headers) . "\n";
        foreach ($rows as $row) {
            $content .= implode(',', array_map(
                fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"',
                $row
            )) . "\n";
        }

        $tmp = tempnam(sys_get_temp_dir(), 'import_test_');
        file_put_contents($tmp, $content);

        return new UploadedFile($tmp, 'alumnos.csv', 'text/csv', null, true);
    }

    private function postImport(UploadedFile $csv): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)
            ->postJson('/api/v1/alumnos/importar', [
                'archivo'          => $csv,
                'ciclo_escolar_id' => $this->ciclo->id,
            ]);
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    /** @test */
    public function test_import_exitoso_crea_alumno_padre_e_inscripcion(): void
    {
        $csv = $this->makeCsv([[
            'Juan', 'García', 'López',
            'GALJ050312HMCRPN08', '2005-03-12',
            '1er Grado', 'A',
            'Pedro García Martínez', 'pedro@email.com', '3311234567',
        ]]);

        $response = $this->postImport($csv);

        $response->assertOk()
            ->assertJsonPath('data.importados', 1)
            ->assertJsonPath('data.actualizados', 0)
            ->assertJsonPath('data.errores', []);

        $this->assertDatabaseHas('alumnos', [
            'escuela_id'       => $this->escuela->id,
            'nombre'           => 'Juan',
            'apellido_paterno' => 'García',
            'curp'             => 'GALJ050312HMCRPN08',
        ]);

        $this->assertDatabaseHas('padres', [
            'escuela_id' => $this->escuela->id,
            'email'      => 'pedro@email.com',
        ]);

        $this->assertDatabaseHas('inscripciones', [
            'ciclo_escolar_id' => $this->ciclo->id,
            'grupo_id'         => $this->grupo->id,
            'estado'           => 'activa',
        ]);
    }

    /** @test */
    public function test_duplicado_por_curp_actualiza_el_alumno_existente(): void
    {
        // Alumno pre-existente con la misma CURP
        Alumno::create([
            'escuela_id'       => $this->escuela->id,
            'nombre'           => 'Juan Viejo',
            'apellido_paterno' => 'García',
            'curp'             => 'GALJ050312HMCRPN08',
            'fecha_nacimiento' => '2005-03-12',
        ]);

        $csv = $this->makeCsv([[
            'Juan Actualizado', 'García', 'López',
            'GALJ050312HMCRPN08', '2005-03-12',
            '1er Grado', 'A',
            'Pedro García', 'pedro@email.com', '',
        ]]);

        $response = $this->postImport($csv);

        $response->assertOk()
            ->assertJsonPath('data.importados', 0)
            ->assertJsonPath('data.actualizados', 1)
            ->assertJsonPath('data.errores', []);

        // El alumno existente fue actualizado
        $this->assertDatabaseHas('alumnos', [
            'curp'   => 'GALJ050312HMCRPN08',
            'nombre' => 'Juan Actualizado',
        ]);

        // Sólo debe existir un registro (no duplicado)
        $this->assertSame(
            1,
            Alumno::where('curp', 'GALJ050312HMCRPN08')->count()
        );
    }

    /** @test */
    public function test_fila_con_campo_requerido_faltante_genera_error(): void
    {
        $csv = $this->makeCsv([[
            // nombre vacío
            '', 'García', 'López',
            '', '2005-03-12',
            '1er Grado', 'A',
            'Pedro García', 'pedro@email.com', '',
        ]]);

        $response = $this->postImport($csv);

        $response->assertOk()
            ->assertJsonPath('data.importados', 0)
            ->assertJsonPath('data.actualizados', 0);

        $response->assertJsonCount(1, 'data.errores');

        $this->assertSame('nombre', $response->json('data.errores.0.campo'));
        $this->assertSame(2, $response->json('data.errores.0.fila')); // fila 2 = primera de datos

        // Ningún alumno fue creado
        $this->assertDatabaseMissing('alumnos', ['escuela_id' => $this->escuela->id]);
    }

    /** @test */
    public function test_fila_con_grado_inexistente_genera_error(): void
    {
        $csv = $this->makeCsv([[
            'María', 'López', '',
            '', '2006-04-10',
            'Grado Inexistente', 'Z',
            'Ana López', 'ana@email.com', '',
        ]]);

        $response = $this->postImport($csv);

        $response->assertOk()
            ->assertJsonPath('data.importados', 0);

        $this->assertSame('grado_nombre', $response->json('data.errores.0.campo'));
    }

    /** @test */
    public function test_import_mixto_crea_los_validos_y_reporta_los_invalidos(): void
    {
        $csv = $this->makeCsv([
            // fila válida
            ['Juan', 'García', '', '', '2005-03-12', '1er Grado', 'A', 'Pedro García', 'pedro@email.com', ''],
            // fila inválida (nombre vacío)
            ['', 'Pérez', '', '', '2006-01-01', '1er Grado', 'A', 'Luis Pérez', 'luis@email.com', ''],
        ]);

        $response = $this->postImport($csv);

        $response->assertOk()
            ->assertJsonPath('data.importados', 1)
            ->assertJsonPath('data.actualizados', 0);

        $response->assertJsonCount(1, 'data.errores');

        $this->assertDatabaseHas('alumnos', [
            'escuela_id'       => $this->escuela->id,
            'nombre'           => 'Juan',
            'apellido_paterno' => 'García',
        ]);
    }

    /** @test */
    public function test_ciclo_de_otro_tenant_devuelve_404(): void
    {
        $otraEscuela = Escuela::create([
            'nombre' => 'Otra Escuela',
            'slug'   => 'otra-escuela',
            'email'  => 'otra@escuela.com',
        ]);

        $otraNivel = Nivel::create(['escuela_id' => $otraEscuela->id, 'nombre' => 'primaria']);

        $otroCiclo = CicloEscolar::create([
            'escuela_id'  => $otraEscuela->id,
            'nivel_id'    => $otraNivel->id,
            'nombre'      => '2024-2025',
            'fecha_inicio' => '2024-08-01',
            'fecha_fin'   => '2025-06-30',
            'activo'      => true,
        ]);

        $csv = $this->makeCsv([
            ['Juan', 'García', '', '', '2005-03-12', '1er Grado', 'A', 'Pedro García', 'pedro@email.com', ''],
        ]);

        $this->actingAs($this->user)
            ->postJson('/api/v1/alumnos/importar', [
                'archivo'          => $csv,
                'ciclo_escolar_id' => $otroCiclo->id, // ciclo de otro tenant
            ])
            ->assertNotFound();
    }
}
