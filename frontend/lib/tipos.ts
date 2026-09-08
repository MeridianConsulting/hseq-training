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

export type TipoPeriodoDashboard = "mensual" | "trimestral" | "semestral" | "anual";

export type PeriodoDashboard = {
  tipo: TipoPeriodoDashboard;
  anio: number;
  mes: number | null;
  trimestre: number | null;
  semestre: number | null;
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

export type KpiEficacia = {
  promedio: number | null;
  evaluaciones: number;
};

export type KpiSoportes = {
  requieren: number;
  con_soporte: number;
  pendientes: number;
  porcentaje: number | null;
};

export type KpiHoras = {
  programadas: number;
  ejecutadas: number;
};

export type ResumenDashboard = {
  periodo: PeriodoDashboard;
  alcance: {
    proceso: string;
    proyecto: string | null;
  };
  cobertura: {
    general: KpiCumplimiento;
    induccion: KpiCumplimiento;
    tareas_criticas: KpiCumplimiento;
  };
  eficacia: {
    general: KpiEficacia;
    induccion: KpiEficacia;
    tareas_criticas: KpiEficacia;
  };
  soportes: KpiSoportes;
  horas: {
    general: KpiHoras;
    induccion: KpiHoras;
    critica: KpiHoras;
  };
  cumplimiento_general: KpiCumplimiento;
  cumplimiento_induccion: KpiCumplimiento;
  cumplimiento_tareas_criticas: KpiCumplimiento;
  poblacion: {
    activos: number;
    inactivos: number;
  };
  opciones: {
    procesos: ProcesoCronograma[];
    proyectos: string[];
  };
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
  plan_anual_id?: number;
  capacitacion_id: number;
  codigo: string;
  tema: string;
  objetivo: string;
  horas: number | null;
  metodologia: string | null;
  mes: number;
  mes_nombre: string;
  fecha_programada: string | null;
  cantidad_programada: number;
  anio: number;
  proceso_id: number | null;
  proceso_nombre: string | null;
  ambito: string | null;
  proyecto: string | null;
  estado_programacion: string;
  estado_operativo: string;
  requiere_evaluacion?: boolean;
  requiere_certificado?: boolean;
  vigencia_id?: number | null;
  vigencia_nombre?: string | null;
  vigencia_cantidad?: number | null;
  vigencia_unidad?: string | null;
  cargos_aplicables: CargoCorporativo[];
  sesiones: SesionCronograma[];
};

export type TrabajadorCronograma = {
  asignacion_id: number;
  persona_id_ext: number;
  numero_documento: string;
  persona_nombre: string;
  nombre_cargo: string | null;
  estado_asignacion: string;
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
  proyecto?: string | null;
  total: number;
  estado_plan: string;
  procesos: ProcesoCronograma[];
  proyectos?: string[];
  items?: ItemCronograma[];
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
  cumplimiento_id: number | null;
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
  tipo_alerta?: string | null;
  nota_evaluacion?: number | null;
  resultado?: string | null;
  requiere_soporte?: boolean;
  soportes_count?: number;
  tiene_soporte?: boolean;
};

export type ResumenAlertas = {
  vencidas: number;
  proximas_30: number;
};

export type ListaAlertas = {
  items: AlertaProximaVencer[];
  pagination: {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
  };
  resumen: ResumenAlertas;
};

export type OpcionesAlertas = {
  procesos: { proceso_id: number; nombre: string }[];
  proyectos: string[];
  cargos: { cargo_id: number; nombre_cargo: string }[];
  capacitaciones?: { capacitacion_id: number; codigo: string; nombre: string }[];
  tipos_capacitacion?: { tipo_capacitacion_id: number; nombre: string }[];
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
  cargo?: string | null;
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

export type ConsultaCumplimiento = {
  asignacion_id: number;
  persona_id_ext: number;
  persona_nombre: string | null;
  numero_documento: string | null;
  cargo: string | null;
  proyecto: string | null;
  estado_laboral: string | null;
  proceso_id: number | null;
  proceso_nombre: string | null;
  capacitacion_id: number;
  capacitacion_codigo: string | null;
  capacitacion_nombre: string | null;
  tipo_nombre: string | null;
  es_tarea_critica: boolean;
  origen: string | null;
  fecha_asignacion: string | null;
  fecha_limite_cumplimiento: string | null;
  cumplimiento_id: number | null;
  sesion_id: number | null;
  fecha_realizacion: string | null;
  fecha_vencimiento: string | null;
  resultado: string | null;
  horas_efectivas: number | null;
  requiere_evaluacion: boolean;
  nota_minima: number;
  nota_evaluacion: number | null;
  evaluacion_aprobada: boolean | null;
  requiere_certificado: boolean;
  requiere_listado_asistencia: boolean;
  vigencia_nombre: string | null;
  estado_calculado: string;
  soportes: SoporteCumplimiento[];
  soportes_count: number;
};

export type DetalleConsultaCumplimiento = {
  asignacion: ConsultaCumplimiento;
  trabajador: {
    persona_id_ext: number;
    nombre: string | null;
    documento: string | null;
    cargo: string | null;
    proyecto: string | null;
    estado_laboral: string | null;
  };
  capacitacion: {
    capacitacion_id: number;
    codigo: string | null;
    nombre: string | null;
    requiere_evaluacion: boolean;
    nota_minima: number;
    requiere_certificado: boolean;
    requiere_listado_asistencia: boolean;
    es_tarea_critica: boolean;
    vigencia_nombre: string | null;
    tipo_nombre: string | null;
  };
  aplicabilidad: {
    aplica: boolean;
    matriz_aplicabilidad_id: number | null;
    activa: boolean | null;
    obligatoria: boolean | null;
    proceso_nombre: string | null;
    proyecto: string | null;
    fuente: string;
  };
  obligacion: {
    asignacion_id: number;
    origen: string | null;
    fecha_asignacion: string | null;
    fecha_limite_cumplimiento: string | null;
    fuente: string;
  };
  programacion: { fecha_programada: string | null; fuente: string };
  ejecucion: {
    sesion_id: number | null;
    fecha_sesion: string | null;
    fecha_realizacion: string | null;
    fuente: string;
  };
  asistencia: { estado: string | null; valida: boolean; fuente: string };
  evaluacion: {
    requiere: boolean;
    nota_obtenida: number | null;
    nota_minima: number;
    aprobada: boolean | null;
    fuente: string;
  };
  soportes: {
    requiere_certificado: boolean;
    requiere_listado: boolean;
    items: SoporteCumplimiento[];
    fuente: string;
  };
  vigencia: {
    nombre: string | null;
    fecha_vencimiento: string | null;
    periodicidad_nombre: string | null;
    origen_periodicidad: string | null;
    fuente: string;
  };
  estado_actual: string;
};

export type OpcionesConsultaCumplimientos = {
  procesos: { proceso_id: number; nombre: string }[];
  proyectos: string[];
  cargos: CargoCorporativo[];
  tipos: { tipo_capacitacion_id: number; nombre: string }[];
  capacitaciones: { capacitacion_id: number; codigo: string; nombre: string }[];
};

export type SituacionTrabajadorCumplimiento = {
  trabajador: {
    persona_id_ext: number;
    nombre: string | null;
    documento: string | null;
    cargo: string | null;
    proyecto: string | null;
    estado_laboral: string | null;
    cargo_id?: number | null;
  };
  aplicables: CapacitacionAplicable[];
  items: ConsultaCumplimiento[];
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

export type TotalesReporte = {
  asignadas: number;
  completadas: number;
  pendientes: number;
  vencidas: number;
  proximas: number;
  programadas?: number;
  ejecutadas?: number;
  porcentaje: number | null;
  horas: number;
  asistieron?: number;
  tarde?: number;
  ausentes?: number;
  convocados?: number;
};

export type ResultadoReporte = {
  items: Record<string, unknown>[];
  pagination: {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
  };
  totales: TotalesReporte;
  titulo: string;
  filtros_etiqueta: Record<string, string>;
  trabajador?: FichaTrabajadorReporte;
  historial_cargo?: PeriodoHistorial[];
  historial_proyecto?: PeriodoHistorial[];
  historial_proceso?: PeriodoHistorial[];
  grupos?: GrupoHistorial[];
};

export type FichaTrabajadorReporte = {
  persona_id: number;
  documento: string | null;
  nombre: string | null;
  correo: string | null;
  cargo: string | null;
  proyecto: string | null;
  fecha_ingreso: string | null;
  estado: string | null;
};

export type PeriodoHistorial = {
  cargo?: string | null;
  proyecto?: string | null;
  proceso?: string | null;
  vigente_desde: string | null;
  vigente_hasta: string | null;
  fuente: "laboral" | "asignaciones";
  origen?: string | null;
};

export type GrupoHistorial = {
  proyecto: string;
  asignadas: number;
  items: Record<string, unknown>[];
};

export const TIPOS_REPORTE: { id: string; etiqueta: string }[] = [
  { id: "cumplimiento_general", etiqueta: "Cumplimiento general" },
  { id: "cumplimiento_trabajador", etiqueta: "Cumplimiento por trabajador" },
  { id: "cumplimiento_cargo", etiqueta: "Cumplimiento por cargo" },
  { id: "cumplimiento_proceso", etiqueta: "Cumplimiento por proceso" },
  { id: "historial_trabajador", etiqueta: "Historial del trabajador" },
  { id: "cumplimiento_proyecto", etiqueta: "Cumplimiento por proyecto" },
  { id: "vencidas", etiqueta: "Capacitaciones vencidas" },
  { id: "proximas", etiqueta: "Próximas a vencer" },
  { id: "pendientes", etiqueta: "Capacitaciones pendientes" },
  { id: "horas", etiqueta: "Horas de capacitación" },
  { id: "asistencia", etiqueta: "Asistencia" },
  { id: "inducciones", etiqueta: "Inducciones" },
  { id: "reinducciones", etiqueta: "Reinducciones" },
  { id: "tareas_criticas", etiqueta: "Tareas críticas" },
  { id: "evidencias_faltantes", etiqueta: "Evidencias faltantes" },
];

export type ProximasAsignaciones = {
  total: number;
  items: Asignacion[];
};

export type ProcesoPersona = {
  proceso_id: number;
  nombre: string;
};

export type PersonaCorporativa = {
  persona_id: number;
  numero_documento: string;
  tipo_documento_id: number | null;
  tipo_documento_nombre?: string | null;
  tipo_documento_abreviatura?: string | null;
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
  procesos?: ProcesoPersona[];
  sincronizacion?: {
    creadas: number;
    omitidas: number;
    creadas_especiales?: string[];
    error: string | null;
  };
};

export type OpcionesPersonal = {
  procesos: ProcesoCronograma[];
  proyectos: string[];
  cargos: CargoCorporativo[];
};

export type CapacitacionAplicable = {
  matriz_aplicabilidad_id: number | null;
  capacitacion_id: number | null;
  capacitacion_codigo: string | null;
  capacitacion_nombre: string | null;
  proceso_id: number | null;
  proceso_nombre: string | null;
  proyecto: string | null;
  periodicidad_nombre: string | null;
};

export type HistorialSesionPersona = {
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

export type PerfilTrabajador = {
  ficha: PersonaCorporativa;
  aplicables: CapacitacionAplicable[];
  pendientes: Asignacion[];
  ejecutadas: Asignacion[];
  cumplimientos: Cumplimiento[];
  evaluaciones: Cumplimiento[];
  soportes: SoporteCumplimiento[];
  historial_sesiones: HistorialSesionPersona[];
  alertas: AlertaProximaVencer[];
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
  vigencia_cantidad?: number | null;
  vigencia_unidad?: string | null;
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

export type OpcionesMatriz = {
  procesos: { proceso_id: number; nombre: string }[];
  proyectos: string[];
  cargos: CargoCorporativo[];
  capacitaciones: {
    capacitacion_id: number;
    codigo: string;
    nombre: string;
    es_tarea_critica: boolean;
  }[];
};

export type CeldaMatriz = {
  cargo_id_ext: number;
  capacitacion_id: number;
  matriz_aplicabilidad_id: number;
  activa: boolean;
};

export type VistaMatriz = {
  proceso_id: number;
  proceso_nombre: string;
  proyecto: string | null;
  cargos: CargoCorporativo[];
  cargos_catalogo?: CargoCorporativo[];
  capacitaciones: {
    capacitacion_id: number;
    codigo: string;
    nombre: string;
    es_tarea_critica: boolean;
  }[];
  celdas: CeldaMatriz[];
};

export type ResultadoSincronizarMatriz = {
  creadas: number;
  reactivadas: number;
  inactivadas: number;
  vista: VistaMatriz;
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

export type CapacitacionPlanOpcion = {
  capacitacion_id: number;
  codigo: string;
  nombre: string;
  es_tarea_critica: boolean;
  duracion_estimada_horas: number | null;
  tipo_nombre: string | null;
  vigencia_nombre: string | null;
  vigencia_cantidad: number | null;
  vigencia_unidad: string | null;
  objetivo: string | null;
  modalidad_nombre: string | null;
};

export type OpcionesPlanAnual = {
  procesos: { proceso_id: number; nombre: string }[];
  proyectos: string[];
  capacitaciones: CapacitacionPlanOpcion[];
};

export type DetallePlanAnual = {
  plan_detalle_id: number;
  capacitacion_id: number;
  capacitacion_codigo: string;
  capacitacion_nombre: string;
  capacitacion_objetivo: string | null;
  tipo_nombre: string | null;
  duracion_estimada_horas: number | null;
  es_tarea_critica: boolean;
  evaluacion: boolean;
  vigencia_nombre: string | null;
  modalidad_nombre: string | null;
  fecha_programada: string | null;
  mes_programado: number;
  mes_nombre: string;
  trimestre: number;
  cantidad_programada: number;
  proceso_id: number | null;
  proceso_nombre: string | null;
  ambito: string | null;
  proyecto: string | null;
  cargos_aplicables: CargoCorporativo[];
};

export type PlanAnual = {
  plan_anual_id: number;
  anio: number;
  estado: "BORRADOR" | "EN_REVISION" | "APROBADO" | string;
  total_programadas: number;
  total_horas?: number;
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

export type CambioAuditoria = {
  campo: string;
  etiqueta: string;
  anterior: unknown;
  nuevo: unknown;
};

export type RegistroAuditoria = {
  auditoria_id: number;
  usuario_id_ext: number | null;
  nombre_usuario: string | null;
  accion: string;
  entidad: string | null;
  entidad_id: number | null;
  detalle: unknown;
  valor_anterior: unknown;
  valor_nuevo: unknown;
  cambios: CambioAuditoria[];
  origen: string | null;
  ip_origen: string | null;
  created_at: string | null;
};

export type ConteoMigracion = {
  detectados?: number;
  validos?: number;
  inconsistencias?: number;
  existentes?: number;
  excel?: number;
  importados?: number;
  rechazados?: number;
  sistema?: number;
  diferencia?: number;
};

export type ResumenMigracion = {
  hojas_detectadas: string[];
  hojas_faltantes: string[];
  estructura_valida: boolean;
  anio_programa: number;
  trabajadores: ConteoMigracion;
  capacitaciones: ConteoMigracion;
  matriz: ConteoMigracion;
  cumplimientos: ConteoMigracion;
  asignaciones_pendientes?: ConteoMigracion;
  inconsistencias_total: number;
  errores: number;
  advertencias: number;
};

export type InconsistenciaMigracion = {
  hoja: string;
  fila: number;
  tipo: string;
  identificador: string;
  campo: string;
  valor: string;
  motivo: string;
  severidad: "Error" | "Advertencia" | string;
};

export type Migracion = {
  migracion_id: number;
  nombre_archivo: string;
  anio_programa: number;
  estado: string;
  usuario_nombre: string | null;
  created_at: string | null;
  confirmada_at: string | null;
  resumen: ResumenMigracion;
  inconsistencias_total: number;
  conteos: Record<string, ConteoMigracion> | null;
};
