ALTER TABLE `citizen_requests`
ADD COLUMN `assigned_to_committee_id` INT NULL AFTER `assigned_to_department_id`,
ADD INDEX `idx_citizen_requests_assigned_committee` (`assigned_to_committee_id`);

ALTER TABLE `citizen_requests`
ADD CONSTRAINT `fk_citizen_requests_committee`
FOREIGN KEY (`assigned_to_committee_id`) REFERENCES `municipal_committees`(`id`)
ON DELETE SET NULL ON UPDATE CASCADE;

