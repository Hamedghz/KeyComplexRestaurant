<?php

if (!function_exists('hrPermissionCatalog')) {
    function hrPermissionCatalog(): array {
        return [
            'hr_platform_access' => 'HR Platform Access',
            'hr_platform_manage' => 'HR Platform Manage',
            'business_standards_manage' => 'Business Standards Manage',
            'business_standards_view' => 'Business Standards View',
            'hr_tests_manage' => 'HR Tests Manage',
            'hr_tests_assign' => 'HR Tests Assign',
            'hr_tests_take' => 'HR Tests Take',
            'hr_tests_view_results' => 'HR Tests View Results',
            'hr_tests_reports' => 'HR Tests Reports',
            'hr_tests_retake_approve' => 'HR Tests Retake Approval',
            'hr_duties_manage' => 'HR Duties Manage',
            'hr_checklists_manage' => 'HR Checklists Manage',
            'hr_checklists_submit' => 'HR Checklists Submit',
            'hr_checklists_approve_manager' => 'HR Checklists Manager Approval',
            'hr_checklists_approve_inspector' => 'HR Checklists Inspector Approval',
            'hr_checklists_report' => 'HR Checklists Report',
            'hr_kpi_manage' => 'HR KPI Manage',
            'hr_kpi_assign' => 'HR KPI Assign',
            'hr_kpi_entry' => 'HR KPI Entry',
            'hr_kpi_report' => 'HR KPI Report',
            'planner_access' => 'Planner Access',
            'planner_manage_own' => 'Planner Manage Own',
            'planner_assign' => 'Planner Assign',
            'planner_view_team' => 'Planner View Team',
            'planner_report' => 'Planner Report',
            'okr_access' => 'OKR Access',
            'okr_manage' => 'OKR Manage',
            'okr_review' => 'OKR Review',
            'tmo_access' => 'TMO Access',
            'tmo_manage' => 'TMO Manage',
            'tmo_report' => 'TMO Report',
        ];
    }
}

if (!function_exists('hrCan')) {
    function hrCan(?array $admin, string $permission, array $fallbackRoles = ['manager', 'admin', 'super_admin']): bool {
        return adminPermissionAllows($admin, $permission, $fallbackRoles);
    }
}
