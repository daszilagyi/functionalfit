export interface GoogleCalendarSyncConfig {
  id: number;
  name: string;
  google_calendar_id: string;
  room_id: number | null;
  room?: {
    id: number;
    name: string;
    site: string;
  };
  sync_enabled: boolean;
  sync_direction: 'import' | 'export' | 'both';
  service_account_json: string | null;
  sync_options: Record<string, any> | null;
  last_import_at: string | null;
  last_export_at: string | null;
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
  sync_logs?: GoogleCalendarSyncLog[];
}

export interface GoogleCalendarSyncLog {
  id: number;
  sync_config_id: number;
  sync_config?: GoogleCalendarSyncConfig;
  operation: 'import' | 'export';
  status: 'pending' | 'in_progress' | 'completed' | 'failed' | 'cancelled';
  started_at: string | null;
  completed_at: string | null;
  events_processed: number;
  events_created: number;
  events_updated: number;
  events_skipped: number;
  events_failed: number;
  conflicts_detected: number;
  filters: {
    start_date?: string;
    end_date?: string;
    room_id?: number;
  } | null;
  conflicts: GoogleCalendarConflict[] | null;
  error_message: string | null;
  metadata: Record<string, any> | null;
  created_at: string;
  updated_at: string;
}

export interface GoogleCalendarConflict {
  google_event_id: string;
  google_summary: string;
  google_start: string;
  google_end: string;
  conflicting_events: {
    event_id: number;
    type: string;
    start: string;
    end: string;
    overlap_minutes: number;
  }[];
}

export interface CreateSyncConfigInput {
  name: string;
  google_calendar_id: string;
  room_id?: number | null;
  sync_enabled?: boolean;
  sync_direction: 'import' | 'export' | 'both';
  service_account_json?: string | null;
  sync_options?: Record<string, any> | null;
}

export interface UpdateSyncConfigInput extends Partial<CreateSyncConfigInput> {}

export interface ImportEventsInput {
  sync_config_id: number;
  start_date: string;
  end_date: string;
  room_id?: number | null;
  auto_resolve_conflicts?: boolean;
}

export interface ExportEventsInput {
  sync_config_id: number;
  start_date: string;
  end_date: string;
  room_id?: number | null;
  overwrite_existing?: boolean;
}

export interface ResolveConflictsInput {
  resolutions: Record<string, 'overwrite' | 'skip'>;
}

export interface TestConnectionInput {
  google_calendar_id: string;
}

export interface TestConnectionResponse {
  success: boolean;
  message: string;
  data?: {
    calendar_summary: string;
    calendar_timezone: string;
  };
}

export interface SyncOperation {
  type: 'import' | 'export';
  configId: number;
  configName: string;
  startDate: string;
  endDate: string;
  roomId?: number;
  options?: Record<string, any>;
}
