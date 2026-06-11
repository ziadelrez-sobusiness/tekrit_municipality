-- Phase 5E Projects Unification Migration
-- This script safely migrates data from development_projects to projects
-- with ID shifting to avoid conflicts, and updates the citizen_requests FK.

START TRANSACTION;

-- 1. Create a backup table
CREATE TABLE IF NOT EXISTS _backup_development_projects_phase5e AS SELECT * FROM development_projects;

-- 2. Update foreign keys in citizen_requests (shifting IDs)
UPDATE citizen_requests 
SET project_id = project_id + 1000 
WHERE project_id IN (SELECT id FROM development_projects);

-- 3. Insert migrated projects into the unified projects table
INSERT INTO projects (
    id,
    project_name,
    description,
    project_goal,
    location,
    budget,
    budget_currency_id,
    progress_percentage,
    status,
    manager_id,
    is_featured,
    start_date,
    end_date,
    beneficiaries_count,
    beneficiaries_description,
    gallery_images,
    before_images,
    after_images,
    is_public
)
SELECT 
    id + 1000,
    project_name,
    project_description,
    project_goal,
    project_location,
    project_cost,
    currency_id,
    completion_percentage,
    CASE project_status
        WHEN 'مقترح' THEN 'مخطط'
        WHEN 'منفذ' THEN 'مكتمل'
        ELSE project_status
    END,
    project_manager_id,
    is_featured,
    start_date,
    end_date,
    beneficiaries_count,
    beneficiaries_description,
    project_images,
    before_images,
    after_images,
    1 -- Mark as public since development_projects were public
FROM development_projects
WHERE (id + 1000) NOT IN (SELECT id FROM projects);

COMMIT;
