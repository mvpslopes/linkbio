-- ============================================================
--  Depoimentos: novos comentários entram pendentes (approved=0)
--  Execute no phpMyAdmin se a tabela já existir.
-- ============================================================

ALTER TABLE `testimonials`
  MODIFY `approved` TINYINT(1) NOT NULL DEFAULT 0;
