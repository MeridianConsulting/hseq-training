/**
 * Tipos que reflejan las respuestas de la API.
 */

export type RolHseq = {
  role_id: number;
  nombre: string;
};

export type UsuarioSesion = {
  usuario_id: number;
  nombre_usuario: string;
  correo: string;
  rol: string;
  estado: string;
  ultimo_acceso: string | null;
  roles: RolHseq[];
  permisos: string[];
};

export type LoginResponse = {
  token: string;
  token_type: string;
  expires_in: number;
  usuario: UsuarioSesion;
};

export type EstadoPing = {
  nombre: string;
  conectada: boolean;
  tablas?: number;
  error?: string;
};

export type PingRespuesta = {
  ok: boolean;
  app: string;
  base_capacitaciones: EstadoPing;
  base_personal: EstadoPing;
};

export type TipoPeriodoDashboard = "mensual" | "trimestral" | "anual";

export type PeriodoDashboard = {
  tipo: TipoPeriodoDashboard;
  anio: number;
  mes: number | null;
  trimestre: number | null;
  desde: string;
  hasta: string;
  etiqueta: string;
};

export type KpiCumplimiento = {
  programado: number;
  ejecutado: number;
  porcentaje: number | null;
  sin_programado: boolean;
};

export type TemaEficacia = {
  capacitacion_id: number;
  codigo: string;
  nombre: string;
  promedio: number;
  evaluaciones: number;
};

export type TemaHoras = {
  capacitacion_id: number;
  codigo: string;
  nombre: string;
  horas: number;
};

export type ResumenDashboard = {
  periodo: PeriodoDashboard;
  cumplimiento_general: KpiCumplimiento;
  cumplimiento_induccion: KpiCumplimiento;
  cumplimiento_tareas_criticas: KpiCumplimiento;
  eficacia_por_tema: TemaEficacia[];
  horas_por_tema: TemaHoras[];
  estados: Record<string, number>;
  pendientes: number;
  alertas_activas: number;
  alertas: AlertaVencimiento[];
};

export type ProcesoCronograma = {
  proceso_id: number;
  nombre: string;
};

export type SesionCronograma = {
  sesion_id: number;
  plan_detalle_id: number | null;
  capacitacion_id: number;
  capacitacion_codigo: string;
  capacitacion_nombre: string;
  fecha_hora: string;
  fecha: string | null;
  hora: string | null;
  modalidad_id: number;
  modalidad_nombre: string | null;
  ubicacion_id: number | null;
  ubicacion_nombre: string | null;
  enlace_virtual: string | null;
  proveedor_id: number | null;
  proveedor_nombre: string | null;
  cupo_maximo: number;
  convocados: number;
  disponibles: number;
  cupo_completo: boolean;
  estado: string;
  requiere_certificado?: boolean;
  requiere_evaluacion?: boolean;
  nota_minima?: number | null;
};

export type ParticipanteSesion = {
  sesion_participante_id: number;
  asignacion_id: number;
  persona_id_ext: number;
  persona_nombre: string;
  numero_documento: string;
  persona_estado: string | null;
  estado_asistencia: string;
  motivo_ausencia: string | null;
  observacion: string | null;
  registrado_por_usuario_id_ext: number | null;
  updated_at: string | null;
  cumplimiento_id: number | null;
  cumplimiento_resultado: string | null;
  fecha_realizacion: string | null;
  horas_efectivas: number | null;
  fecha_vencimiento: string | null;
  nota_evaluacion?: number | null;
};

export type ResumenAsistencia = {
  convocados: number;
  asistieron: number;
  tarde: number;
  ausentes: number;
  pendientes: number;
};

export type IntentoSesion = {
  sesion_participante_id: number;
  sesion_id: number;
  asignacion_id: number;
  persona_id_ext: number;
  estado_asistencia: string;
  motivo_ausencia: string | null;
  observacion: string | null;
  fecha_hora: string;
  fecha: string | null;
  sesion_estado: string;
  capacitacion_codigo: string;
  capacitacion_nombre: string;
  updated_at: string | null;
};

export type ConvocableSesion = {
  asignacion_id: number;
  persona_id_ext: number;
  persona_nombre: string;
  numero_documento: string;
  origen: string;
  en_plan: boolean;
};

export type ContextoSesion = {
  plan_detalle_id: number;
  plan_anual_id: number;
  anio: number;
  plan_estado: string;
  mes_programado: number;
  capacitacion_id: number;
  capacitacion_codigo: string;
  capacitacion_nombre: string;
  modalidad_default_id: number | null;
  proveedor_default_id: number | null;
  modalidades: ItemCatalogo[];
  ubicaciones: ItemCatalogo[];
  proveedores: ItemCatalogo[];
  items: ConvocableSesion[];
};

export type DetalleSesion = SesionCronograma & {
  plan_anual_id: number | null;
  plan_estado: string | null;
  anio: number | null;
  observaciones: string | null;
  participantes: ParticipanteSesion[];
  resumen?: ResumenAsistencia;
  reprogramacion?: {
    seleccionados: number;
    reprogramados: number;
    omitidas: number;
    errores: number;
  };
};

export type ItemCronograma = {
  plan_detalle_id: number;
  capacitacion_id: number;
  codigo: string;
  tema: string;
  objetivo: string;
  horas: number | null;
  metodologia: string | null;
  mes: number;
  mes_nombre: string;
  cantidad_programada: number;
  anio: number;
  proceso_id: number | null;
  proceso_nombre: string | null;
  sesiones: SesionCronograma[];
};

export type MesCronograma = {
  mes: number;
  nombre: string;
  total: number;
  items: ItemCronograma[];
};

export type TableroCronograma = {
  periodo: PeriodoDashboard;
  proceso_id: number | null;
  proceso_nombre: string | null;
  total: number;
  estado_plan: string;
  procesos: ProcesoCronograma[];
  meses: MesCronograma[];
};

export type AlertaVencimiento = {
  asignacion_id: number;
  persona_id_ext: number;
  capacitacion_id: number;
  proyecto: string | null;
  fecha_limite_cumplimiento: string | null;
  fecha_realizacion: string | null;
  fecha_vencimiento: string | null;
  estado_calculado: string;
  tipo_alerta: string;
  fecha_alerta: string | null;
};

export type AlertaProximaVencer = {
  cumplimiento_id: number;
  asignacion_id: number;
  persona_id_ext: number | null;
  trabajador: string | null;
  documento: string | null;
  cargo: string | null;
  cargo_id_ext: number | null;
  proceso: string | null;
  proceso_id: number | null;
  proyecto: string | null;
  capacitacion_id: number | null;
  capacitacion_codigo: string | null;
  capacitacion_nombre: string | null;
  fecha_realizacion: string | null;
  fecha_vencimiento: string | null;
  dias_restantes: number;
  estado: string;
};

export type OpcionesAlertas = {
  procesos: { proceso_id: number; nombre: string }[];
  proyectos: string[];
  cargos: { cargo_id: number; nombre_cargo: string }[];
};

export type Asignacion = {
  asignacion_id: number;
  persona_id_ext: number;
  persona_nombre: string | null;
  numero_documento: string | null;
  contrato_id_ext: number | null;
  capacitacion_id: number;
  capacitacion_codigo: string;
  capacitacion_nombre: string;
  fecha_asignacion: string;
  fecha_limite_cumplimiento: string;
  origen: string;
  periodicidad_nombre: string | null;
  obligatoria: boolean | null;
  cargo_id_ext: number | null;
  ambito: "ADMINISTRACION" | "PROYECTO" | null;
  proyecto: string | null;
  estado_calculado: string;
  tiene_cumplimiento: boolean;
  cumplimiento_id: number | null;
  cumplimiento_sesion_id: number | null;
  cumplimiento_resultado: string | null;
  fecha_realizacion: string | null;
  horas_efectivas: number | null;
  fecha_vencimiento: string | null;
  dias_restantes: number | null;
  etiqueta_dias: string | null;
};

export type SoporteCumplimiento = {
  soporte_id: number;
  cumplimiento_id: number | null;
  tipo_soporte: string;
  nombre_archivo: string;
  mime_type: string | null;
  tamano_bytes: number | null;
  cargado_por_usuario_id_ext: number | null;
  created_at: string | null;
};

export type Cumplimiento = {
  cumplimiento_id: number;
  asignacion_id: number;
  sesion_id: number | null;
  persona_id_ext: number | null;
  persona_nombre: string | null;
  numero_documento: string | null;
  capacitacion_id: number | null;
  capacitacion_codigo: string | null;
  capacitacion_nombre: string | null;
  requiere_certificado?: boolean;
  requiere_evaluacion?: boolean;
  nota_minima?: number | null;
  nota_evaluacion?: number | null;
  evaluacion_aprobada?: boolean | null;
  fecha_realizacion: string | null;
  resultado: string | null;
  horas_efectivas: number | null;
  fecha_vencimiento: string | null;
  observaciones: string | null;
  estado_calculado?: string | null;
  estado_vigencia?: string;
  soportes_count?: number;
  soportes?: SoporteCumplimiento[];
};

export type PreviewItemCumplimiento = {
  asignacion_id: number;
  cumplimiento_id: number | null;
  numero_documento: string | null;
  persona_nombre: string | null;
  estado_asistencia: string | null;
  resultado_actual: string | null;
  periodicidad_nombre: string | null;
  periodicidad_cantidad: number | null;
  periodicidad_unidad: string | null;
  origen_periodicidad: string;
  etiqueta_periodicidad: string;
  fecha_vencimiento: string | null;
  etiqueta_vencimiento: string;
  puede_registrar: boolean;
  motivo: string | null;
  requiere_certificado?: boolean;
  soportes_count?: number;
  requiere_evaluacion?: boolean;
  nota_minima?: number | null;
  nota_evaluacion?: number | null;
  evaluacion_aprobada?: boolean | null;
};

export type PreviewCumplimiento = {
  sesion_id: number;
  fecha_realizacion: string;
  items: PreviewItemCumplimiento[];
  periodicidades_distintas: boolean;
  aviso: string | null;
};

export type ResultadoMasivoCumplimiento = {
  procesados: number;
  completados: number;
  errores: number;
  items: Cumplimiento[];
};

export type ResultadoEvaluaciones = {
  procesados: number;
  items: Cumplimiento[];
};

export type EvidenciaFaltante = {
  cumplimiento_id: number;
  asignacion_id: number;
  persona_id_ext: number | null;
  trabajador: string | null;
  documento: string | null;
  capacitacion: string | null;
  fecha_realizacion: string | null;
  estado: string;
  requiere_certificado: boolean;
  soportes_count: number;
};

export type ProximasAsignaciones = {
  total: number;
  items: Asignacion[];
};

export type PersonaCorporativa = {
  persona_id: number;
  numero_documento: string;
  tipo_documento_id: number | null;
  nombre_completo: string;
  estado: string;
  cargo_id: number | null;
  cargo: string | null;
  correo_corporativo: string | null;
  correo_personal: string | null;
  celular: string | null;
  contrato_id: number | null;
  numero_contrato: string | null;
  proyecto: string | null;
  contrato_fecha_inicio: string | null;
  contrato_fecha_terminacion: string | null;
  sincronizacion?: {
    creadas: number;
    omitidas: number;
    error: string | null;
  };
};

export type CargoCorporativo = {
  cargo_id: number;
  nombre_cargo: string;
};

export type TipoDocumentoCorporativo = {
  tipo_documento_id: number;
  descripcion: string;
  abreviatura: string;
};

export type RechazoCargaPersonal = {
  fila: number;
  documento: string;
  nombre: string;
  estado: string;
  motivo: string;
};

export type ResultadoCargaPersonal = {
  total_procesados: number;
  total_importados: number;
  total_rechazados: number;
  rechazados: RechazoCargaPersonal[];
};

export type Capacitacion = {
  capacitacion_id: number;
  codigo: string;
  nombre: string;
  objetivo: string;
  descripcion_temario: string | null;
  categoria_id: number | null;
  categoria_nombre: string | null;
  tipo_capacitacion_id: number | null;
  tipo_nombre: string | null;
  duracion_estimada_horas: number;
  criticidad: "BAJA" | "MEDIA" | "ALTA";
  es_tarea_critica: boolean;
  responsable: string | null;
  proveedor_default_id: number | null;
  proveedor_nombre: string | null;
  periodicidad_default_id: number | null;
  periodicidad_nombre: string | null;
  vigencia_id: number | null;
  vigencia_nombre: string | null;
  modalidad_default_id: number | null;
  modalidad_nombre: string | null;
  evaluacion: boolean;
  nota_minima: number | null;
  certificado: boolean;
  requiere_listado_asistencia: boolean;
  fuente_normativa_id: number | null;
  fuente_normativa_nombre: string | null;
  estado: "ACTIVA" | "INACTIVA";
};

export type FilaMatriz = {
  matriz_aplicabilidad_id: number;
  capacitacion_id: number;
  capacitacion_codigo: string | null;
  capacitacion_nombre: string | null;
  cargo_id_ext: number | null;
  cargo_nombre: string | null;
  area_id: number | null;
  area_nombre: string | null;
  proceso_id: number | null;
  proceso_nombre: string | null;
  ambito: "ADMINISTRACION" | "PROYECTO" | null;
  proyecto: string | null;
  periodicidad_id: number | null;
  periodicidad_nombre: string | null;
  obligatoria: boolean;
  activa: boolean;
};

export type AsignacionEnPlan = {
  asignacion_id: number;
  persona_id_ext: number | null;
  persona_nombre: string | null;
  numero_documento: string | null;
  capacitacion_id: number;
  capacitacion_codigo: string;
  capacitacion_nombre: string;
  origen: string;
  proyecto: string | null;
};

export type DetallePlanAnual = {
  plan_detalle_id: number;
  capacitacion_id: number;
  capacitacion_codigo: string;
  capacitacion_nombre: string;
  mes_programado: number;
  mes_nombre: string;
  trimestre: number;
  cantidad_programada: number;
  proceso_id: number | null;
  proceso_nombre: string | null;
  ambito: string | null;
  proyecto: string | null;
  asignaciones: AsignacionEnPlan[];
};

export type PlanAnual = {
  plan_anual_id: number;
  anio: number;
  estado: "BORRADOR" | "EN_REVISION" | "APROBADO" | string;
  total_programadas: number;
  aprobado_por_usuario_id_ext: number | null;
  fecha_aprobacion: string | null;
  creado_por_usuario_id_ext: number | null;
  created_at: string | null;
  detalles?: DetallePlanAnual[];
};

export type TipoCatalogo = {
  tipo: string;
  etiqueta: string;
  permite_inactivar: boolean;
  campos: string[];
};

export type ItemCatalogo = {
  [clave: string]: unknown;
};

export type RegistroAuditoria = {
  auditoria_id: number;
  usuario_id_ext: number | null;
  nombre_usuario: string | null;
  accion: string;
  entidad: string | null;
  entidad_id: number | null;
  detalle: unknown;
  ip_origen: string | null;
  created_at: string | null;
};
