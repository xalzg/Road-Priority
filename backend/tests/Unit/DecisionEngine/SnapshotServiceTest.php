<?php

namespace Tests\Unit\DecisionEngine;

use App\Services\DecisionEngine\DecisionEngineException;
use App\Services\DecisionEngine\SnapshotService;
use PHPUnit\Framework\TestCase;

class SnapshotServiceTest extends TestCase
{
    use LoadsReferenceFixture;

    private SnapshotService $snapshots;

    protected function setUp(): void
    {
        parent::setUp();
        $this->snapshots = new SnapshotService();
    }

    private function buildLampiranInputs(): array
    {
        [$criteria, $alternatives, $ref] = $this->lampiranC();
        $weights = [];
        foreach ($criteria as $j => $c) {
            $weights[$c['code']] = $ref['ahp']['weights'][$j];
        }
        $weightVersion = [
            'version_id' => 1,
            'label' => 'v1-ahp',
            'status' => 'approved',
            'cr' => $ref['ahp']['cr'],
            'is_consistent' => true,
            'weights' => $weights,
        ];

        return [$criteria, $weightVersion, $alternatives];
    }

    /** Tes minimum 8 & 9: snapshot membekukan input/output dan dapat direproduksi identik. */
    public function test_snapshot_freezes_inputs_and_reproduces_identically(): void
    {
        [$criteria, $weightVersion, $alternatives] = $this->buildLampiranInputs();

        $snapshot = $this->snapshots->createSnapshot($criteria, $weightVersion, $alternatives, false, ['window_days' => 90]);

        // Mutasi array pemanggil setelah snapshot dibuat tidak boleh mengubah isi snapshot
        $weightVersion['weights']['K1'] = 0.10;
        $alternatives[0]['values']['K1'] = 1;

        $repro = $this->snapshots->reproduce($snapshot);
        $this->assertTrue($repro['reproducible']);
        $this->assertTrue($repro['input_hash_matches']);
        $this->assertSame(['A2', 'A1', 'A3', 'A4'], array_column($snapshot['items'], 'code'));
        $this->assertSame('K1', $snapshot['items'][0]['largest_driving_criterion']);
        $this->assertStringContainsString('Prototipe penelitian', $snapshot['disclaimer']);
    }

    /** Tes minimum 9: membuat versi bobot baru tidak mengubah snapshot run lama. */
    public function test_creating_new_weight_version_does_not_change_old_snapshot(): void
    {
        [$criteria, $weightVersionV1, $alternatives] = $this->buildLampiranInputs();
        $runV1 = $this->snapshots->createSnapshot($criteria, $weightVersionV1, $alternatives);
        $beforeV1 = $runV1;

        $weightVersionV2 = [
            'version_id' => 2,
            'label' => 'v2-equal',
            'status' => 'approved',
            'cr' => 0.0,
            'is_consistent' => true,
            'weights' => ['K1' => 1 / 3, 'K2' => 1 / 3, 'K3' => 1 / 3],
        ];
        $runV2 = $this->snapshots->createSnapshot($criteria, $weightVersionV2, $alternatives);

        $this->assertSame($beforeV1, $runV1);
        $this->assertNotSame($runV1['input_hash'], $runV2['input_hash']);
        $this->assertSame(['A2', 'A1', 'A3', 'A4'], array_column($runV1['items'], 'code'));
        $this->assertSame(['A1', 'A4', 'A3', 'A2'], array_column($runV2['items'], 'code'));
        $this->assertTrue($this->snapshots->reproduce($runV1)['reproducible']);
    }

    public function test_official_run_rejects_unapproved_or_inconsistent_weights(): void
    {
        [$criteria, $weightVersion, $alternatives] = $this->buildLampiranInputs();

        $draft = $weightVersion;
        $draft['status'] = 'draft';
        try {
            $this->snapshots->createSnapshot($criteria, $draft, $alternatives, false);
            $this->fail('Harus menolak versi draft pada run resmi');
        } catch (DecisionEngineException $e) {
            $this->assertSame('weight_version_not_approved', $e->errorCode());
        }

        $inconsistent = $weightVersion;
        $inconsistent['cr'] = 0.15;
        $inconsistent['is_consistent'] = false;
        try {
            $this->snapshots->createSnapshot($criteria, $inconsistent, $alternatives, false);
            $this->fail('Harus menolak bobot tidak konsisten pada run resmi');
        } catch (DecisionEngineException $e) {
            $this->assertSame('weight_version_inconsistent', $e->errorCode());
        }

        // Tetapi diizinkan bila is_simulation = true
        $sim = $this->snapshots->createSnapshot($criteria, $inconsistent, $alternatives, true);
        $this->assertTrue($sim['is_simulation']);
    }

    public function test_official_run_rejects_demo_data_but_simulation_allows_it(): void
    {
        [$criteria, $weightVersion, $alternatives] = $this->buildLampiranInputs();
        $alternatives[0]['is_demo'] = true;
        $alternatives[0]['data_origin'] = 'demo';

        try {
            $this->snapshots->createSnapshot($criteria, $weightVersion, $alternatives, false);
            $this->fail('Harus menolak data demo pada run resmi');
        } catch (DecisionEngineException $e) {
            $this->assertSame('demo_data_in_official_run', $e->errorCode());
        }

        $sim = $this->snapshots->createSnapshot($criteria, $weightVersion, $alternatives, true);
        $this->assertTrue($sim['is_simulation']);
    }

    public function test_reproduce_detects_tampered_snapshot(): void
    {
        [$criteria, $weightVersion, $alternatives] = $this->buildLampiranInputs();
        $snapshot = $this->snapshots->createSnapshot($criteria, $weightVersion, $alternatives);

        $tamperedInput = $snapshot;
        $tamperedInput['alternatives_snapshot'][0]['values']['K1'] = 2;
        try {
            $this->snapshots->reproduce($tamperedInput);
            $this->fail('Harus mendeteksi perubahan input snapshot');
        } catch (DecisionEngineException $e) {
            $this->assertSame('snapshot_hash_mismatch', $e->errorCode());
        }

        $tamperedOutput = $snapshot;
        $tamperedOutput['items'][0]['closeness'] += 0.01;
        try {
            $this->snapshots->reproduce($tamperedOutput);
            $this->fail('Harus mendeteksi perubahan nilai output snapshot');
        } catch (DecisionEngineException $e) {
            $this->assertSame('snapshot_reproduction_failed', $e->errorCode());
        }
    }
}

