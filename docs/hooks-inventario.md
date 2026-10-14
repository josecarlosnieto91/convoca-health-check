# Convoca API Pública v3.0 — Hooks y Shortcodes

> Instantánea legible de la API pública. La fuente de verdad es `api/api-v3.0.json`
> (lo regenera `scripts/api-extract.py`) y este documento se regenera con
> `scripts/build-hooks-inventory-doc.py`. `scripts/check-inventory-doc.py` contrasta
> los dos en CI: si el documento miente, el check falla.

## Resumen

| Recurso | Cantidad |
|---------|----------|
| Hooks | 205 |
| Shortcodes | 27 |

## Shortcodes

| Shortcode | Plugin |
|-----------|--------|
| `[convoca_cuando]` | convoca-core |
| `[convoca_donde]` | convoca-core |
| `[convoca_menu]` | convoca-core |
| `[convoca_relacionadas]` | convoca-core |
| `[convoca_socials]` | convoca-core |
| `[convoca_stats]` | convoca-core |
| `[convoca_alta_socio]` | convoca-members |
| `[convoca_mi_area]` | convoca-members |
| `[convoca_mi_perfil]` | convoca-members |
| `[convoca_renovar]` | convoca-members |
| `[convoca_verificar_certificado]` | convoca-members |
| `[convoca_verificar_socio]` | convoca-members |
| `[convoca_voluntariado]` | convoca-members |
| `[convoca_actividad_meta]` | convoca-enroll |
| `[convoca_evaluacion]` | convoca-enroll |
| `[convoca_form_inscripcion]` | convoca-enroll |
| `[convoca_inscripcion_actual]` | convoca-enroll |
| `[convoca_inscripcion_page]` | convoca-enroll |
| `[convoca_panel_reservas]` | convoca-enroll |
| `[convoca_pago]` | convoca-gateway |
| `[convoca_pago_ko]` | convoca-gateway |
| `[convoca_pago_ok]` | convoca-gateway |
| `[convoca_boton_apuntarse]` | convoca-shifts |
| `[convoca_calendario]` | convoca-shifts |
| `[convoca_proximos_turnos]` | convoca-shifts |
| `[convoca_resumen_turnos]` | convoca-shifts |
| `[convoca_assistant]` | convoca-assistant |

## Hooks por plugin

### convoca-core (47 hooks)

| Hook | Tipo | Origen |
|------|------|--------|
| `convoca_admin_appearance_url` | dispara | includes/admin-appearance.php |
| `convoca_common_webhook_sslverify` | dispara | includes/Webhook_Manager.php |
| `convoca_continue_access_codes` | escucha | convoca-core.php |
| `convoca_document_theme` | dispara | includes/Utils.php |
| `convoca_email_copy_enabled` | dispara | includes/Email_Copy.php |
| `convoca_email_copy_payload` | dispara | includes/Email_Copy.php |
| `convoca_email_copy_recipients` | dispara | includes/Email_Copy.php |
| `convoca_email_footer_links` | dispara | includes/Email_Links.php |
| `convoca_email_panel_url` | dispara | includes/Email_Links.php |
| `convoca_enroll_asistencia_cambiada` | escucha | includes/Webhook_Manager.php |
| `convoca_enroll_inscripcion_cancelada` | escucha | includes/Webhook_Manager.php |
| `convoca_enroll_inscripcion_nueva` | escucha | includes/Webhook_Manager.php |
| `convoca_event_meta_legacy_keys` | dispara | includes/event-meta.php |
| `convoca_gateway_payment_completed` | escucha | includes/Webhook_Manager.php |
| `convoca_gateway_payment_failed` | escucha | includes/Webhook_Manager.php, includes/Notifications.php |
| `convoca_inscripcion_pendiente_pago` | escucha | includes/Notifications.php |
| `convoca_license_api_url` | dispara | includes/License_Manager.php |
| `convoca_license_validate` | escucha | includes/License_Manager.php |
| `convoca_log_cleanup` | escucha | convoca-core.php |
| `convoca_log_level` | dispara | includes/Logger.php |
| `convoca_log_purge` | escucha | convoca-core.php |
| `convoca_log_retention_days` | dispara | includes/Logger.php |
| `convoca_member_created` | escucha | includes/Notifications.php |
| `convoca_members_created` | escucha | includes/Webhook_Manager.php |
| `convoca_members_estado_changed` | escucha | includes/Webhook_Manager.php |
| `convoca_members_hora_aprobada` | escucha | includes/Webhook_Manager.php |
| `convoca_members_hora_rechazada` | escucha | includes/Webhook_Manager.php |
| `convoca_members_hours_submitted` | escucha | includes/Webhook_Manager.php, includes/Notifications.php |
| `convoca_members_membership_expired` | escucha | includes/Webhook_Manager.php |
| `convoca_members_payment_reminder_sent` | escucha | includes/Webhook_Manager.php |
| `convoca_members_unsubscribe_request` | escucha | includes/Notifications.php |
| `convoca_module_registered` | dispara | includes/class-module-registry.php |
| `convoca_need_common_assets` | dispara | includes/admin-global-menu.php |
| `convoca_pdf_allowed_html` | dispara | includes/Signature.php |
| `convoca_pdf_html_safe_keys` | dispara | includes/Signature.php |
| `convoca_register_module` | dispara | includes/class-module-registry.php |
| `convoca_security_digest` | dispara y escucha | includes/Security_Monitor.php |
| `convoca_security_thresholds` | dispara | includes/Security_Monitor.php |
| `convoca_site_stats` | dispara | includes/front-shortcodes.php |
| `convoca_social_links` | dispara | includes/front-shortcodes.php |
| `convoca_voluntario_aprobado` | escucha | includes/Notifications.php |
| `convoca_voluntario_pendiente` | escucha | includes/Notifications.php |
| `convoca_webhook_retry` | escucha | includes/Webhook_Manager.php |
| `convoca_webhook_retry_backoff` | dispara | includes/Webhook_Manager.php |
| `convoca_weekly_event` | escucha | includes/Memory_Report.php |
| `convoca_wizard_legacy_shortcodes` | dispara | includes/Admin_Setup_Wizard.php |
| `convoca_wizard_system_pages` | dispara | includes/Admin_Setup_Wizard.php |

### convoca-members (68 hooks)

| Hook | Tipo | Origen |
|------|------|--------|
| `convoca_alta_sin_js_email` | dispara | templates/form-alta.php |
| `convoca_alta_sin_js_telefono` | dispara | templates/form-alta.php |
| `convoca_daily_event` | escucha | includes/Cron_Manager.php |
| `convoca_documento_horas` | dispara | includes/Texto_PDF.php |
| `convoca_email_bienvenida` | dispara (retirado) | includes/Estados.php |
| `convoca_email_cambiado` | dispara (retirado) | includes/Rest_API.php, public/class-mi-area.php |
| `convoca_email_confirm` | dispara (retirado) | includes/Rest_API.php |
| `convoca_email_objetivo_voluntariado` | dispara (retirado) | includes/Voluntariado_Manager.php |
| `convoca_email_providers` | dispara | includes/Email_Verifier.php |
| `convoca_email_recordatorio_pago` | dispara (retirado) | includes/Estados.php |
| `convoca_email_solicitud` | dispara (retirado) | includes/Process_Member.php |
| `convoca_email_verify_phone` | dispara (retirado) | includes/Rest_API.php |
| `convoca_estado_changed` | dispara (retirado) | includes/Estados.php |
| `convoca_gamification_tracks` | dispara | includes/Voluntariado_Gamification.php |
| `convoca_gateway_payment_completed` | escucha | includes/Payment_Listener.php |
| `convoca_gateway_payment_failed` | escucha | includes/Payment_Listener.php |
| `convoca_hours_submitted` | dispara (retirado) | includes/Rest_API.php |
| `convoca_member_created` | dispara | admin/class-admin-import-csv.php, public/class-form-handler.php |
| `convoca_member_objetivo_completado` | dispara (retirado) | includes/Voluntariado_Manager.php |
| `convoca_member_unsubscribe_request` | dispara (retirado) | includes/Rest_API.php |
| `convoca_members_alta_document_url` | dispara | includes/Identidad.php |
| `convoca_members_auto_renewal_completed` | dispara | includes/Cron_Manager.php |
| `convoca_members_auto_renewal_created` | dispara | includes/Cron_Manager.php |
| `convoca_members_certificado_validez_anios` | dispara | includes/Certificate_Generator.php |
| `convoca_members_created` | dispara | includes/Process_Member.php |
| `convoca_members_cuota_pagada` | dispara | includes/Payment_Listener.php |
| `convoca_members_documento_url` | dispara | includes/Identidad.php |
| `convoca_members_email_bienvenida` | dispara y escucha | includes/Email_Manager.php, includes/Estados.php |
| `convoca_members_email_cambiado` | dispara | includes/Rest_API.php, public/class-mi-area.php |
| `convoca_members_email_confirm` | dispara y escucha | includes/Rest_API.php, includes/Email_Manager.php |
| `convoca_members_email_credenciales` | escucha | includes/Email_Manager.php |
| `convoca_members_email_objetivo_voluntariado` | dispara y escucha | includes/Voluntariado_Manager.php, includes/Email_Manager.php |
| `convoca_members_email_recordatorio_pago` | dispara y escucha | includes/Email_Manager.php, includes/Estados.php |
| `convoca_members_email_renovacion` | escucha | includes/Email_Manager.php |
| `convoca_members_email_renovacion_automatica` | escucha | includes/Email_Manager.php |
| `convoca_members_email_renovacion_completada` | escucha | includes/Email_Manager.php |
| `convoca_members_email_solicitud` | dispara y escucha | includes/Email_Manager.php, includes/Process_Member.php |
| `convoca_members_email_verify_phone` | dispara y escucha | includes/Rest_API.php, includes/Email_Manager.php |
| `convoca_members_estado_changed` | dispara y escucha | includes/Estados.php |
| `convoca_members_hora_aprobada` | dispara y escucha | admin/class-admin-horas.php, includes/Certificate_Generator.php, includes/Hours_Manager.php |
| `convoca_members_hora_rechazada` | dispara | admin/class-admin-horas.php, includes/Hours_Manager.php |
| `convoca_members_hours_submitted` | dispara | includes/Rest_API.php |
| `convoca_members_interest_areas` | dispara | public/class-form-voluntariado.php |
| `convoca_members_membership_expired` | dispara | includes/Cron_Manager.php |
| `convoca_members_miembro_dado_de_baja` | dispara | includes/CPT_Miembro.php |
| `convoca_members_nombre_entidad` | dispara | includes/Identidad.php |
| `convoca_members_objetivo_completado` | dispara | includes/Voluntariado_Manager.php |
| `convoca_members_payment_reminder_sent` | dispara | includes/Cron_Manager.php |
| `convoca_members_phone_verificado` | dispara | public/class-mi-area.php |
| `convoca_members_plans` | dispara | includes/CPT_Miembro.php |
| `convoca_members_reactivado` | dispara | includes/Process_Member.php |
| `convoca_members_renewal_reminder_sent` | dispara | includes/Cron_Manager.php |
| `convoca_members_sensitive_meta` | dispara | includes/Audit_Logger.php |
| `convoca_members_unsubscribe_request` | dispara | includes/Rest_API.php |
| `convoca_mi_area_links` | dispara | public/class-mi-area.php |
| `convoca_miembro_creado` | dispara (retirado) | includes/Process_Member.php |
| `convoca_miembro_cuota_pagada` | dispara (retirado) | includes/Payment_Listener.php |
| `convoca_miembro_dado_de_baja` | dispara (retirado) | includes/CPT_Miembro.php |
| `convoca_miembro_reactivado` | dispara (retirado) | includes/Process_Member.php |
| `convoca_panel_socio_sin_js_email` | dispara | public/class-mi-area.php |
| `convoca_phone_verificado` | dispara (retirado) | public/class-mi-area.php |
| `convoca_voluntariado_sin_js_email` | dispara | templates/form-voluntariado.php |
| `convoca_voluntariado_sin_js_telefono` | dispara | templates/form-voluntariado.php |
| `convoca_voluntario_aprobado` | dispara y escucha | convoca-members.php, includes/Admin_Voluntariado.php, includes/PDF_Document.php |
| `convoca_voluntario_aprobado_attachments` | dispara y escucha | includes/Admin_Voluntariado.php, includes/PDF_Document.php |
| `convoca_voluntario_pendiente` | dispara | public/class-form-voluntariado.php |
| `convoca_voluntario_revocado` | dispara | includes/Admin_Voluntariado.php |
| `convoca_weekly_event` | escucha | includes/Cron_Manager.php |

### convoca-enroll (35 hooks)

| Hook | Tipo | Origen |
|------|------|--------|
| `convoca_after_horas_voluntario_actualizadas` | dispara | includes/Volunteer_Hour_Tracker.php |
| `convoca_asistencia_cambiada` | dispara (retirado) | includes/Motor_Inscripcion.php |
| `convoca_enroll_activity_reminder` | escucha | includes/Activity_Reminder_Cron.php |
| `convoca_enroll_aportacion_label` | dispara | includes/Utils.php |
| `convoca_enroll_asistencia_cambiada` | dispara y escucha | includes/Motor_Inscripcion.php, includes/Volunteer_Hour_Tracker.php, includes/Webhook_Dispatcher.php |
| `convoca_enroll_cleanup_orphan_codes` | escucha | convoca-enroll.php |
| `convoca_enroll_daily_maintenance` | escucha | includes/Maintenance.php |
| `convoca_enroll_eval_reminder` | escucha | includes/Eval_Reminder_Cron.php |
| `convoca_enroll_feedback` | escucha | includes/Email_Automation.php |
| `convoca_enroll_form_en_ficha` | dispara | includes/CPT_Actividad.php |
| `convoca_enroll_form_repetido` | dispara | includes/CPT_Actividad.php |
| `convoca_enroll_google_photos_share` | escucha | includes/Email_Automation.php |
| `convoca_enroll_inscripcion_cancelada` | dispara y escucha | includes/Motor_Inscripcion.php, includes/Webhook_Dispatcher.php, includes/Google_Sheets.php |
| `convoca_enroll_inscripcion_confirmada` | dispara y escucha | includes/Motor_Inscripcion.php, includes/Webhook_Dispatcher.php, includes/Google_Sheets.php |
| `convoca_enroll_inscripcion_nueva` | dispara y escucha | includes/Motor_Inscripcion.php, includes/Webhook_Dispatcher.php, includes/Google_Sheets.php |
| `convoca_enroll_inscripcion_promovida` | dispara y escucha | includes/Motor_Inscripcion.php, includes/Webhook_Dispatcher.php, includes/Google_Sheets.php |
| `convoca_enroll_late_cancel_admin_email` | dispara | includes/Motor_Inscripcion.php |
| `convoca_enroll_process_email_queue` | escucha | includes/Email_Queue.php |
| `convoca_enroll_process_webhook_queue` | escucha | includes/Webhook_Dispatcher.php |
| `convoca_enroll_reminder_1hora` | escucha | includes/Email_Automation.php |
| `convoca_enroll_reminder_24h` | escucha | includes/Email_Automation.php |
| `convoca_enroll_reminder_7dias` | escucha | includes/Email_Automation.php |
| `convoca_evaluacion_completada` | dispara | public/class-formulario-evaluacion.php |
| `convoca_gateway_payment_completed` | escucha | includes/Payment_Listener.php |
| `convoca_gateway_payment_failed` | escucha | includes/Payment_Listener.php |
| `convoca_horas_voluntario_actualizadas` | dispara (retirado) | includes/Volunteer_Hour_Tracker.php |
| `convoca_inscripcion_cancelada` | dispara (retirado) | includes/Motor_Inscripcion.php |
| `convoca_inscripcion_confirmada` | dispara (retirado) | includes/Motor_Inscripcion.php |
| `convoca_inscripcion_nueva` | dispara (retirado) | includes/Motor_Inscripcion.php |
| `convoca_inscripcion_promovida` | dispara (retirado) | includes/Motor_Inscripcion.php |
| `convoca_inscripcion_sin_js_email` | dispara | templates/form-inscripcion.php |
| `convoca_inscripcion_sin_js_telefono` | dispara | templates/form-inscripcion.php |
| `convoca_panel_sin_js_email` | dispara | templates/panel-reservas.php |
| `convoca_social_publish` | escucha | convoca-enroll.php |
| `convoca_social_token_healthcheck` | escucha | social/class-social-healthcheck.php |

### convoca-gateway (13 hooks)

| Hook | Tipo | Origen |
|------|------|--------|
| `convoca_gateway_bank_entity` | dispara | includes/Admin_Settings.php, includes/Payment_Handler.php |
| `convoca_gateway_expiry_notice` | dispara y escucha | convoca-gateway.php, includes/Link_Expiry.php |
| `convoca_gateway_payment_completed` | dispara y escucha | convoca-gateway.php, includes/Email_Notifications.php, includes/Payment_Handler.php |
| `convoca_gateway_payment_failed` | dispara y escucha | includes/Email_Notifications.php, includes/Payment_Handler.php |
| `convoca_gateway_payment_refunded` | dispara | includes/Admin_Payments.php |
| `convoca_gateway_pending_reminder` | dispara y escucha | convoca-gateway.php, includes/Email_Notifications.php |
| `convoca_gateway_receipt_payer_name` | dispara | includes/Receipt_View.php |
| `convoca_gateway_redsys_allowed_ips` | dispara | includes/Payment_Handler.php |
| `convoca_gateway_resend_email` | dispara | includes/Admin_Payments.php |
| `convoca_gateway_site_ips` | dispara | includes/Payment_Handler.php |
| `convoca_gateway_volver_url` | dispara | includes/Payment_Handler.php |
| `convoca_payment_completed` | dispara (retirado) | includes/Payment_Handler.php, includes/Admin_Payments.php |
| `convoca_payment_failed` | dispara (retirado) | includes/Payment_Handler.php |

### convoca-shifts (15 hooks)

| Hook | Tipo | Origen |
|------|------|--------|
| `convoca_after_horas_voluntario_actualizadas` | dispara | includes/Hour_Sync.php |
| `convoca_horas_voluntario_actualizadas` | dispara (retirado) | includes/Hour_Sync.php |
| `convoca_shifts_actividad_add_form_fields` | escucha | includes/cpt-turno.php |
| `convoca_shifts_actividad_edit_form_fields` | escucha | includes/cpt-turno.php |
| `convoca_shifts_admin_email` | dispara | includes/No_Show_Manager.php |
| `convoca_shifts_confirm_signup` | dispara | includes/shortcodes.php |
| `convoca_shifts_daily_event` | escucha | includes/cron.php |
| `convoca_shifts_force_enqueue_assets` | dispara | includes/blocks.php |
| `convoca_shifts_hourly_event` | escucha | includes/cron.php |
| `convoca_shifts_no_show_email_admin` | dispara | includes/No_Show_Manager.php |
| `convoca_shifts_no_show_email_admin_subject` | dispara | includes/No_Show_Manager.php |
| `convoca_shifts_no_show_email_socio` | dispara | includes/No_Show_Manager.php |
| `convoca_shifts_no_show_email_socio_subject` | dispara | includes/No_Show_Manager.php |
| `convoca_voluntario_aprobado` | escucha | includes/admin-approval.php |
| `convoca_voluntario_revocado` | escucha | includes/admin-approval.php |

### convoca-publisher (8 hooks)

| Hook | Tipo | Origen |
|------|------|--------|
| `convoca_publisher_apply_spacing` | dispara y escucha | includes/class-scheduler.php |
| `convoca_publisher_async_publish` | escucha | includes/class-publisher.php |
| `convoca_publisher_retry_backoff` | dispara | includes/class-retry.php |
| `convoca_publisher_retry_event` | dispara y escucha | includes/class-retry.php |
| `convoca_publisher_retry_failed` | dispara | includes/class-retry.php |
| `convoca_publisher_retry_failed_notify_email` | dispara | includes/class-retry.php |
| `convoca_publisher_retry_failed_posts` | escucha | includes/class-scheduler.php |
| `convoca_publisher_scheduled_publish` | dispara y escucha | includes/class-scheduler.php |

### convoca-assistant (3 hooks)

| Hook | Tipo | Origen |
|------|------|--------|
| `convoca_assistant_log_cleanup` | escucha | includes/Statistics.php |
| `convoca_assistant_regenerate` | escucha | includes/Indexer.php |
| `convoca_assistant_regenerate_now` | escucha | includes/Indexer.php |

### convoca-theme (16 hooks)

| Hook | Tipo | Origen |
|------|------|--------|
| `convoca_site_stats` | escucha | patterns/stats.php |
| `convoca_social_links` | dispara | functions.php |
| `convoca_theme_centro_url` | dispara | functions.php |
| `convoca_theme_community_url` | dispara | functions.php |
| `convoca_theme_copyright_extra` | dispara | functions.php |
| `convoca_theme_cta_heading` | dispara | functions.php |
| `convoca_theme_cta_label` | dispara | functions.php |
| `convoca_theme_cta_text` | dispara | functions.php |
| `convoca_theme_cta_url` | dispara | functions.php |
| `convoca_theme_event_country` | dispara | functions.php |
| `convoca_theme_event_region` | dispara | functions.php |
| `convoca_theme_footer_replacements` | dispara | functions.php |
| `convoca_theme_link_labels` | dispara | functions.php |
| `convoca_theme_lugg_url` | dispara | functions.php |
| `convoca_theme_site_links` | dispara | functions.php |
| `convoca_theme_volunteer_email` | dispara | functions.php |
