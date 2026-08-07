# Convoca API Pública v3.0 — Hooks y Shortcodes

> **GENERADO AUTOMÁTICAMENTE** desde el código. No editar a mano.
> Regenerar con: `python3 /tmp/api-extract2.py && python3 /tmp/api-hooks-doc.py`

## Resumen

| Recurso | Cantidad |
|---------|----------|
| Hooks | 127 |
| Shortcodes | 22 |

## Shortcodes

| Shortcode | Plugin |
|-----------|--------|
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
| `[convoca_dark_mode_toggle]` | convoca-theme |

## Hooks

### convoca-core (33 hooks)

| Hook | Tipo | Origen |
|------|------|--------|
| `convoca_admin_appearance_url` | dispara | includes/admin-appearance.php |
| `convoca_common_webhook_sslverify` | dispara | includes/Webhook_Manager.php |
| `convoca_continue_access_codes` | escucha | convoca-core.php |
| `convoca_enroll_asistencia_cambiada` | escucha | includes/Webhook_Manager.php |
| `convoca_enroll_inscripcion_cancelada` | escucha | includes/Webhook_Manager.php |
| `convoca_enroll_inscripcion_nueva` | escucha | includes/Webhook_Manager.php |
| `convoca_gateway_payment_completed` | escucha | includes/Webhook_Manager.php |
| `convoca_gateway_payment_failed` | escucha | includes/Webhook_Manager.php |
| `convoca_hours_submitted` | escucha | includes/Notifications.php |
| `convoca_inscripcion_pendiente_pago` | escucha | includes/Notifications.php |
| `convoca_license_api_url` | dispara | includes/License_Manager.php |
| `convoca_license_validate` | escucha | includes/License_Manager.php |
| `convoca_log_cleanup` | escucha | convoca-core.php |
| `convoca_log_purge` | escucha | convoca-core.php |
| `convoca_member_created` | escucha | includes/Notifications.php |
| `convoca_members_created` | escucha | includes/Webhook_Manager.php |
| `convoca_members_estado_changed` | escucha | includes/Webhook_Manager.php |
| `convoca_members_hora_aprobada` | escucha | includes/Webhook_Manager.php |
| `convoca_members_hora_rechazada` | escucha | includes/Webhook_Manager.php |
| `convoca_members_hours_submitted` | escucha | includes/Webhook_Manager.php |
| `convoca_members_membership_expired` | escucha | includes/Webhook_Manager.php |
| `convoca_members_payment_reminder_sent` | escucha | includes/Webhook_Manager.php |
| `convoca_members_unsubscribe_request` | escucha | includes/Notifications.php |
| `convoca_module_registered` | dispara | includes/class-module-registry.php |
| `convoca_need_common_assets` | dispara | includes/admin-global-menu.php |
| `convoca_payment_failed` | escucha | includes/Notifications.php |
| `convoca_pdf_allowed_html` | dispara | includes/Signature.php |
| `convoca_pdf_html_safe_keys` | dispara | includes/Signature.php |
| `convoca_register_module` | dispara | includes/class-module-registry.php |
| `convoca_voluntario_aprobado` | escucha | includes/Notifications.php |
| `convoca_voluntario_pendiente` | escucha | includes/Notifications.php |
| `convoca_webhook_retry` | escucha | includes/Webhook_Manager.php |
| `convoca_weekly_event` | escucha | includes/Memory_Report.php |

### convoca-members (39 hooks)

| Hook | Tipo | Origen |
|------|------|--------|
| `convoca_daily_event` | escucha | includes/Cron_Manager.php |
| `convoca_email_objetivo_voluntariado` | escucha | includes/Email_Manager.php |
| `convoca_email_providers` | dispara | includes/Email_Verifier.php |
| `convoca_gamification_tracks` | dispara | includes/Voluntariado_Gamification.php |
| `convoca_gateway_payment_completed` | escucha | includes/Payment_Listener.php |
| `convoca_gateway_payment_failed` | escucha | includes/Payment_Listener.php |
| `convoca_member_created` | dispara | admin/class-admin-import-csv.php, public/class-form-handler.php |
| `convoca_members_alta_document_url` | dispara | templates/form-alta.php |
| `convoca_members_auto_renewal_created` | dispara | includes/Cron_Manager.php |
| `convoca_members_created` | dispara | includes/Process_Member.php |
| `convoca_members_cuota_pagada` | dispara | includes/Payment_Listener.php |
| `convoca_members_email_bienvenida` | escucha | includes/Estados.php, includes/Email_Manager.php |
| `convoca_members_email_cambiado` | dispara | includes/Rest_API.php, public/class-mi-area.php |
| `convoca_members_email_confirm` | escucha | includes/Email_Manager.php, includes/Rest_API.php |
| `convoca_members_email_credenciales` | escucha | includes/Email_Manager.php |
| `convoca_members_email_objetivo_voluntariado` | escucha | includes/Voluntariado_Manager.php, includes/Email_Manager.php |
| `convoca_members_email_recordatorio_pago` | escucha | includes/Estados.php, includes/Email_Manager.php |
| `convoca_members_email_renovacion` | escucha | includes/Email_Manager.php |
| `convoca_members_email_renovacion_automatica` | escucha | includes/Email_Manager.php |
| `convoca_members_email_renovacion_completada` | escucha | includes/Email_Manager.php |
| `convoca_members_email_solicitud` | escucha | includes/Process_Member.php, includes/Email_Manager.php |
| `convoca_members_email_verify_phone` | escucha | includes/Email_Manager.php, includes/Rest_API.php |
| `convoca_members_estado_changed` | escucha | includes/Estados.php |
| `convoca_members_hora_aprobada` | escucha | admin/class-admin-horas.php, includes/Hours_Manager.php |
| `convoca_members_hora_rechazada` | dispara | admin/class-admin-horas.php, includes/Hours_Manager.php |
| `convoca_members_hours_submitted` | dispara | includes/Rest_API.php |
| `convoca_members_interest_areas` | dispara | public/class-form-voluntariado.php |
| `convoca_members_membership_expired` | dispara | includes/Cron_Manager.php |
| `convoca_members_objetivo_completado` | dispara | includes/Voluntariado_Manager.php |
| `convoca_members_payment_reminder_sent` | dispara | includes/Cron_Manager.php |
| `convoca_members_phone_verificado` | dispara | public/class-mi-area.php |
| `convoca_members_plans` | dispara | includes/CPT_Miembro.php |
| `convoca_members_renewal_reminder_sent` | dispara | includes/Cron_Manager.php |
| `convoca_members_sensitive_meta` | dispara | includes/Audit_Logger.php |
| `convoca_members_unsubscribe_request` | dispara | includes/Rest_API.php |
| `convoca_voluntario_aprobado` | escucha | convoca-members.php, includes/PDF_Document.php |
| `convoca_voluntario_aprobado_attachments` | escucha | includes/PDF_Document.php |
| `convoca_voluntario_pendiente` | dispara | public/class-form-voluntariado.php |
| `convoca_weekly_event` | escucha | includes/Cron_Manager.php |

### convoca-enroll (24 hooks)

| Hook | Tipo | Origen |
|------|------|--------|
| `convoca_after_horas_voluntario_actualizadas` | dispara | includes/Volunteer_Hour_Tracker.php |
| `convoca_enroll_aportacion_label` | dispara | includes/Utils.php |
| `convoca_enroll_asistencia_cambiada` | escucha | includes/Volunteer_Hour_Tracker.php, includes/Motor_Inscripcion.php |
| `convoca_enroll_cleanup_orphan_codes` | escucha | convoca-enroll.php |
| `convoca_enroll_daily_maintenance` | escucha | includes/Maintenance.php |
| `convoca_enroll_eval_reminder` | escucha | includes/Eval_Reminder_Cron.php |
| `convoca_enroll_feedback` | escucha | includes/Email_Automation.php |
| `convoca_enroll_google_photos_share` | escucha | includes/Email_Automation.php |
| `convoca_enroll_inscripcion_cancelada` | escucha | includes/Motor_Inscripcion.php, includes/Webhook_Dispatcher.php |
| `convoca_enroll_inscripcion_confirmada` | escucha | includes/Motor_Inscripcion.php, includes/Webhook_Dispatcher.php |
| `convoca_enroll_inscripcion_nueva` | escucha | includes/Motor_Inscripcion.php, includes/Webhook_Dispatcher.php |
| `convoca_enroll_inscripcion_promovida` | escucha | includes/Motor_Inscripcion.php, includes/Webhook_Dispatcher.php |
| `convoca_enroll_process_email_queue` | escucha | includes/Email_Queue.php |
| `convoca_enroll_process_webhook_queue` | escucha | includes/Webhook_Dispatcher.php |
| `convoca_enroll_reminder_1hora` | escucha | includes/Email_Automation.php |
| `convoca_enroll_reminder_24h` | escucha | includes/Email_Automation.php |
| `convoca_enroll_reminder_7dias` | escucha | includes/Email_Automation.php |
| `convoca_evaluacion_completada` | dispara | public/class-formulario-evaluacion.php |
| `convoca_gateway_payment_completed` | escucha | includes/Payment_Listener.php |
| `convoca_inscripcion_cancelada` | escucha | includes/Google_Sheets.php, includes/Email_Automation.php |
| `convoca_inscripcion_confirmada` | escucha | includes/Google_Sheets.php, includes/Email_Automation.php |
| `convoca_inscripcion_promovida` | escucha | includes/Google_Sheets.php, includes/Email_Automation.php |
| `convoca_social_publish` | escucha | convoca-enroll.php |
| `convoca_social_token_healthcheck` | escucha | social/class-social-healthcheck.php |

### convoca-gateway (8 hooks)

| Hook | Tipo | Origen |
|------|------|--------|
| `convoca_gateway_bank_entity` | dispara | includes/Payment_Handler.php, includes/Admin_Settings.php |
| `convoca_gateway_payment_completed` | dispara | includes/Admin_Payments.php, includes/Payment_Handler.php |
| `convoca_gateway_payment_failed` | dispara | includes/Payment_Handler.php |
| `convoca_gateway_payment_refunded` | dispara | includes/Admin_Payments.php |
| `convoca_gateway_redsys_allowed_ips` | dispara | includes/Payment_Handler.php |
| `convoca_gateway_resend_email` | dispara | includes/Admin_Payments.php |
| `convoca_payment_completed` | escucha | includes/Email_Notifications.php |
| `convoca_payment_failed` | escucha | includes/Email_Notifications.php |

### convoca-shifts (9 hooks)

| Hook | Tipo | Origen |
|------|------|--------|
| `convoca_after_horas_voluntario_actualizadas` | dispara | includes/Hour_Sync.php |
| `convoca_shifts_actividad_add_form_fields` | escucha | includes/cpt-turno.php |
| `convoca_shifts_actividad_edit_form_fields` | escucha | includes/cpt-turno.php |
| `convoca_shifts_confirm_signup` | dispara | includes/shortcodes.php |
| `convoca_shifts_daily_event` | escucha | includes/cron.php |
| `convoca_shifts_force_enqueue_assets` | dispara | includes/blocks.php |
| `convoca_shifts_hourly_event` | escucha | includes/cron.php |
| `convoca_voluntario_aprobado` | dispara | includes/admin-approval.php |
| `convoca_voluntario_aprobado_attachments` | dispara | includes/admin-approval.php |

### convoca-publisher (3 hooks)

| Hook | Tipo | Origen |
|------|------|--------|
| `convoca_publisher_async_publish` | escucha | includes/class-publisher.php |
| `convoca_publisher_retry_failed_posts` | escucha | includes/class-scheduler.php |
| `convoca_publisher_retry_process` | escucha | includes/class-retry.php |

### convoca-assistant (2 hooks)

| Hook | Tipo | Origen |
|------|------|--------|
| `convoca_assistant_log_cleanup` | escucha | includes/Statistics.php |
| `convoca_assistant_regenerate` | escucha | includes/Indexer.php |

### convoca-theme (9 hooks)

| Hook | Tipo | Origen |
|------|------|--------|
| `convoca_theme_centro_url` | dispara | functions.php |
| `convoca_theme_community_url` | dispara | functions.php |
| `convoca_theme_footer_replacements` | dispara | functions.php |
| `convoca_theme_lugg_url` | dispara | functions.php |
| `convoca_theme_social_facebook` | dispara | functions.php |
| `convoca_theme_social_handle` | dispara | functions.php |
| `convoca_theme_social_instagram` | dispara | functions.php |
| `convoca_theme_social_youtube` | dispara | functions.php |
| `convoca_theme_volunteer_email` | dispara | functions.php |
