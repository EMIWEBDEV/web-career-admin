<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SelfShiftApprovalConfigSeeder extends Seeder
{
    /**
     * Seed approval configs for SELF_SHIFT and SELF_SHIFT_BACKDATE
     * Similar structure to LEADER_SHIFT configs
     */
    public function run(): void
    {
        $now = Carbon::now();

        // =====================================================
        // 1. SELF_SHIFT Config (Future/Same Day)
        // =====================================================
        $selfShiftConfig = DB::table('N_HRIS_HC_Approval_Config')
            ->where('Config_Name', 'SELF_SHIFT')
            ->first();

        if (!$selfShiftConfig) {
            DB::table('N_HRIS_HC_Approval_Config')->insert([
                'Config_Name' => 'SELF_SHIFT',
                'Description' => 'Self Shift Request (Future/Same Day)',
                'Config_Type' => 'CHAIN', // Sequential approval
                'Is_Active' => 'Y',
                'Created_At' => $now,
                'Updated_At' => $now,
            ]);
            
            echo "✓ Created SELF_SHIFT approval config\n";
        } else {
            echo "○ SELF_SHIFT config already exists\n";
        }

        // =====================================================
        // 2. SELF_SHIFT_BACKDATE Config
        // =====================================================
        $selfShiftBackdateConfig = DB::table('N_HRIS_HC_Approval_Config')
            ->where('Config_Name', 'SELF_SHIFT_BACKDATE')
            ->first();

        if (!$selfShiftBackdateConfig) {
            DB::table('N_HRIS_HC_Approval_Config')->insert([
                'Config_Name' => 'SELF_SHIFT_BACKDATE',
                'Description' => 'Self Shift Request (Backdate)',
                'Config_Type' => 'CHAIN',
                'Is_Active' => 'Y',
                'Created_At' => $now,
                'Updated_At' => $now,
            ]);
            
            echo "✓ Created SELF_SHIFT_BACKDATE approval config\n";
        } else {
            echo "○ SELF_SHIFT_BACKDATE config already exists\n";
        }

        // =====================================================
        // 3. Add Config Steps (Example structure)
        // =====================================================
        // Note: Actual steps should be configured based on organizational hierarchy
        // This is a basic example with 2 levels
        
        $this->seedConfigSteps('SELF_SHIFT', [
            [
                'Step_Order' => 1,
                'Step_Name' => 'Atasan Langsung',
                'Step_Type' => 'POSITION', // atau 'INDIVIDUAL' / 'GROUP'
                'Target_Position_Code' => null, // Akan diisi saat runtime berdasarkan requester
                'Target_Employee_Code' => null,
                'Target_Group_Id' => null,
                'Is_Mandatory' => 'Y', // Mandatory approver
                'Stop_If_Rejected' => 'Y',
                'Auto_Approve_After_Days' => null,
                'Description' => 'Approval dari atasan langsung untuk self shift (future/same day)',
            ],
            // Add more steps as needed
        ]);

        $this->seedConfigSteps('SELF_SHIFT_BACKDATE', [
            [
                'Step_Order' => 1,
                'Step_Name' => 'Atasan Langsung',
                'Step_Type' => 'POSITION',
                'Target_Position_Code' => null,
                'Target_Employee_Code' => null,
                'Target_Group_Id' => null,
                'Is_Mandatory' => 'Y',
                'Stop_If_Rejected' => 'Y',
                'Auto_Approve_After_Days' => null,
                'Description' => 'Approval dari atasan langsung untuk self shift backdate',
            ],
            [
                'Step_Order' => 2,
                'Step_Name' => 'Manager HRD',
                'Step_Type' => 'POSITION',
                'Target_Position_Code' => null,
                'Target_Employee_Code' => null,
                'Target_Group_Id' => null,
                'Is_Mandatory' => 'Y', // Bisa diubah jadi 'T' untuk optional
                'Stop_If_Rejected' => 'Y',
                'Auto_Approve_After_Days' => null,
                'Description' => 'Approval tambahan dari HRD untuk backdate request',
            ],
        ]);

        echo "\n✓ Self Shift approval configs seeded successfully!\n";
    }

    /**
     * Seed config steps for a given config name
     */
    private function seedConfigSteps(string $configName, array $steps): void
    {
        $config = DB::table('N_HRIS_HC_Approval_Config')
            ->where('Config_Name', $configName)
            ->first();

        if (!$config) {
            echo "× Config {$configName} not found, skipping steps\n";
            return;
        }

        foreach ($steps as $step) {
            $exists = DB::table('N_HRIS_HC_Approval_Config_Step')
                ->where('Config_Id', $config->Id)
                ->where('Step_Order', $step['Step_Order'])
                ->exists();

            if (!$exists) {
                DB::table('N_HRIS_HC_Approval_Config_Step')->insert(array_merge([
                    'Config_Id' => $config->Id,
                    'Created_At' => Carbon::now(),
                    'Updated_At' => Carbon::now(),
                    'Is_Active' => 'Y',
                ], $step));

                echo "  ✓ Added step {$step['Step_Order']}: {$step['Step_Name']} for {$configName}\n";
            } else {
                echo "  ○ Step {$step['Step_Order']} already exists for {$configName}\n";
            }
        }
    }
}
