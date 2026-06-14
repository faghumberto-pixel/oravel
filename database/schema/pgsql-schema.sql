--
-- PostgreSQL database dump
--

\restrict GVsKhRyAexZrnISnEPmeImTVfCA1FTPuzwaEIese9QsxTtlxqxdGC3PjVFi7Obe

-- Dumped from database version 16.14 (Ubuntu 16.14-0ubuntu0.24.04.1)
-- Dumped by pg_dump version 16.14 (Ubuntu 16.14-0ubuntu0.24.04.1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: public; Type: SCHEMA; Schema: -; Owner: -
--

-- *not* creating schema, since initdb creates it


--
-- Name: SCHEMA public; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON SCHEMA public IS '';


--
-- Name: uuid-ossp; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS "uuid-ossp" WITH SCHEMA public;


--
-- Name: EXTENSION "uuid-ossp"; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION "uuid-ossp" IS 'generate universally unique identifiers (UUIDs)';


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: activity_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.activity_log (
    id bigint NOT NULL,
    log_name character varying(255),
    description text NOT NULL,
    subject_type character varying(255),
    subject_id character varying(255),
    event character varying(255),
    causer_type character varying(255),
    causer_id bigint,
    attribute_changes json,
    properties json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: activity_log_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.activity_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: activity_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.activity_log_id_seq OWNED BY public.activity_log.id;


--
-- Name: asset_movements; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.asset_movements (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    asset_id uuid NOT NULL,
    maintenance_order_id uuid,
    from_location character varying(255),
    to_location character varying(255),
    reason character varying(255),
    moved_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: COLUMN asset_movements.from_location; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.asset_movements.from_location IS 'Origem (Nome da Unidade ou Cliente)';


--
-- Name: COLUMN asset_movements.to_location; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.asset_movements.to_location IS 'Destino (Nome da Unidade ou Cliente)';


--
-- Name: assets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.assets (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    name character varying(255) NOT NULL,
    tag character varying(255),
    patrimonio character varying(255),
    description text,
    serial_number character varying(255),
    status character varying(255) NOT NULL,
    criticality character varying(255) DEFAULT 'medium'::character varying NOT NULL,
    current_location_id uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    acquisition_value numeric(15,2),
    residual_value numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    useful_life_years integer DEFAULT 10 NOT NULL,
    acquisition_date date,
    manual_items json,
    checklist_group_id uuid,
    specification character varying(255),
    manufacturing_year integer,
    client_id uuid,
    is_vehicle boolean DEFAULT false NOT NULL,
    cost_per_km numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    internal_unit_id uuid,
    latitude numeric(10,8),
    longitude numeric(11,8),
    capacity_value character varying(255),
    capacity_unit character varying(255),
    criticality_level_id uuid
);


--
-- Name: attachments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.attachments (
    id uuid NOT NULL,
    maintenance_order_id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    file_path character varying(255) NOT NULL,
    file_name character varying(255) NOT NULL,
    mime_type character varying(255),
    evidence_type character varying(255) NOT NULL,
    latitude numeric(10,8),
    longitude numeric(11,8),
    address character varying(255),
    captured_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: branches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.branches (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    tenant_id uuid NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: checklist_groups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.checklist_groups (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    tenant_id uuid NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: checklist_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.checklist_templates (
    id bigint NOT NULL,
    tenant_id uuid NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: checklist_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.checklist_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: checklist_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.checklist_templates_id_seq OWNED BY public.checklist_templates.id;


--
-- Name: clients; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.clients (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    name character varying(255) NOT NULL,
    cpf_cnpj character varying(255),
    contact_name character varying(255),
    cep character varying(255),
    address character varying(255),
    city character varying(255),
    uf character(2),
    phone character varying(255),
    whatsapp character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    activity_type character varying(255)
);


--
-- Name: companies; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.companies (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    address character varying(255),
    city character varying(255),
    state character varying(255),
    phone character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    tenant_id uuid
);


--
-- Name: companies_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.companies_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: companies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.companies_id_seq OWNED BY public.companies.id;


--
-- Name: contracts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.contracts (
    tenant_id uuid NOT NULL,
    client_id uuid NOT NULL,
    asset_id uuid NOT NULL,
    contract_number character varying(255) NOT NULL,
    status character varying(255) DEFAULT 'Draft'::character varying NOT NULL,
    start_date date NOT NULL,
    price numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    end_date date,
    payment_method character varying(255),
    observations text,
    usage_purpose text,
    required_nrs character varying(255),
    is_active boolean DEFAULT true NOT NULL,
    id uuid NOT NULL
);


--
-- Name: conversation_user; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.conversation_user (
    id bigint NOT NULL,
    conversation_id uuid NOT NULL,
    user_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: conversation_user_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.conversation_user_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: conversation_user_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.conversation_user_id_seq OWNED BY public.conversation_user.id;


--
-- Name: conversations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.conversations (
    id uuid NOT NULL,
    name character varying(255),
    is_group boolean DEFAULT false NOT NULL,
    tenant_id uuid NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: criticality_histories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.criticality_histories (
    id bigint NOT NULL,
    asset_id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    old_level character varying(255),
    new_level character varying(255) NOT NULL,
    origin character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: criticality_histories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.criticality_histories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: criticality_histories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.criticality_histories_id_seq OWNED BY public.criticality_histories.id;


--
-- Name: criticality_levels; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.criticality_levels (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    code character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    color character varying(255) DEFAULT '#ff0000'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: departments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.departments (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    name character varying(255) NOT NULL,
    description character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    code character varying(20)
);


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: internal_communications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.internal_communications (
    id uuid NOT NULL,
    user_id bigint NOT NULL,
    maintenance_order_id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    message text NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: internal_units; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.internal_units (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    name character varying(255) NOT NULL,
    code character varying(255),
    is_active boolean DEFAULT true NOT NULL,
    zip_code character varying(255),
    address character varying(255),
    number character varying(255),
    neighborhood character varying(255),
    city character varying(255),
    state character varying(2),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    cep character varying(10),
    latitude numeric(10,8),
    longitude numeric(11,8)
);


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: locations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.locations (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    address character varying(255) NOT NULL,
    city character varying(255) NOT NULL,
    state character varying(255) NOT NULL,
    zip_code character varying(255) NOT NULL,
    tenant_id uuid NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: maintenance_order_checklists; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.maintenance_order_checklists (
    id uuid NOT NULL,
    maintenance_order_id uuid,
    category character varying(255) NOT NULL,
    item_name character varying(255) NOT NULL,
    instructions text,
    is_completed boolean DEFAULT false NOT NULL,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    department_id uuid,
    code character varying(20),
    tenant_id uuid,
    is_template boolean DEFAULT true NOT NULL,
    checklist_group_id uuid,
    checklist_type character varying(255) DEFAULT 'Preventiva'::character varying NOT NULL,
    section character varying(255)
);


--
-- Name: maintenance_order_delegations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.maintenance_order_delegations (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: maintenance_order_delegations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.maintenance_order_delegations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: maintenance_order_delegations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.maintenance_order_delegations_id_seq OWNED BY public.maintenance_order_delegations.id;


--
-- Name: maintenance_order_material; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.maintenance_order_material (
    id uuid NOT NULL,
    maintenance_order_id uuid NOT NULL,
    material_id uuid NOT NULL,
    quantity numeric(10,2) DEFAULT '1'::numeric NOT NULL,
    cost_at_time numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: maintenance_order_materials; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.maintenance_order_materials (
    id uuid NOT NULL,
    maintenance_order_id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    name character varying(255) NOT NULL,
    quantity integer DEFAULT 1 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    unit_price numeric(15,2) DEFAULT '0'::numeric NOT NULL
);


--
-- Name: maintenance_orders; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.maintenance_orders (
    id uuid NOT NULL,
    asset_id uuid NOT NULL,
    status character varying(255) DEFAULT 'aberta'::character varying NOT NULL,
    priority character varying(255) DEFAULT 'media'::character varying NOT NULL,
    description text,
    solution text,
    created_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    resolved_by bigint,
    resolved_at timestamp(0) without time zone,
    parent_id uuid,
    assigned_technician_id bigint,
    scheduled_at timestamp(0) without time zone,
    workflow_status character varying(255) DEFAULT 'scheduled'::character varying NOT NULL,
    tenant_id uuid,
    photo_path text,
    signature_path text,
    started_at timestamp(0) without time zone,
    finished_at timestamp(0) without time zone,
    cancel_reason text,
    hours_spent numeric(8,2) DEFAULT '0'::numeric NOT NULL,
    technical_notes text,
    os_number character varying(255),
    client_signature text,
    rescheduled_to timestamp(0) without time zone,
    total_time_seconds integer DEFAULT 0 NOT NULL,
    last_timer_start timestamp(0) without time zone,
    reschedule_reason text,
    transfer_reason text,
    service_type character varying(255) DEFAULT 'Interno'::character varying NOT NULL,
    client_id uuid,
    technician_id bigint,
    reported_problem_id uuid,
    maintenance_type character varying(255),
    transport_vehicle_id uuid,
    km_traveled numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    logistics_cost numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    labor_cost numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    material_cost numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    total_order_cost numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    criticality_level_id uuid,
    is_rework boolean DEFAULT false NOT NULL,
    parent_os_id uuid
);


--
-- Name: maintenance_sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.maintenance_sessions (
    id uuid NOT NULL,
    maintenance_order_id uuid NOT NULL,
    user_id bigint NOT NULL,
    started_at timestamp(0) without time zone,
    stopped_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: materials; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.materials (
    id uuid NOT NULL,
    sku character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    unit_cost numeric(10,2) NOT NULL,
    min_stock integer DEFAULT 0 NOT NULL,
    max_stock integer DEFAULT 0 NOT NULL,
    current_stock integer DEFAULT 0 NOT NULL,
    ncm character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    price numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    tenant_id uuid
);


--
-- Name: media; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.media (
    id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id uuid NOT NULL,
    uuid uuid,
    collection_name character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    file_name character varying(255) NOT NULL,
    mime_type character varying(255),
    disk character varying(255) NOT NULL,
    conversions_disk character varying(255),
    size bigint NOT NULL,
    manipulations json NOT NULL,
    custom_properties json NOT NULL,
    generated_conversions json NOT NULL,
    responsive_images json NOT NULL,
    order_column integer,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: media_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.media_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: media_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.media_id_seq OWNED BY public.media.id;


--
-- Name: messages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.messages (
    id uuid NOT NULL,
    conversation_id uuid NOT NULL,
    user_id bigint NOT NULL,
    body text,
    file_path character varying(255),
    file_type character varying(255),
    read_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: model_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_has_permissions (
    permission_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL
);


--
-- Name: model_has_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_has_roles (
    role_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL,
    tenant_id bigint
);


--
-- Name: notifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notifications (
    id uuid NOT NULL,
    type character varying(255) NOT NULL,
    notifiable_type character varying(255) NOT NULL,
    notifiable_id bigint NOT NULL,
    data jsonb NOT NULL,
    read_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permissions (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.permissions_id_seq OWNED BY public.permissions.id;


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name text NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: plans; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.plans (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    price numeric(10,2) NOT NULL,
    billing_cycle character varying(255) DEFAULT 'monthly'::character varying NOT NULL,
    features json,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: reported_problems; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.reported_problems (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    description character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: role_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.role_has_permissions (
    permission_id bigint NOT NULL,
    role_id bigint NOT NULL
);


--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.roles (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    tenant_id bigint
);


--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: stock_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stock_items (
    id uuid NOT NULL,
    tenant_id uuid NOT NULL,
    internal_unit_id uuid NOT NULL,
    name character varying(255) NOT NULL,
    sku character varying(255),
    description text,
    current_stock numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    min_stock numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    unit_price numeric(15,2) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: tenants; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tenants (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    status character varying(255) DEFAULT 'trial'::character varying NOT NULL,
    mrr_value numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    next_billing_date date,
    onboarding_completed boolean DEFAULT false NOT NULL,
    nps_score integer,
    canceled_at timestamp(0) without time zone,
    cancellation_reason text,
    plan_id uuid,
    trial_ends_at timestamp(0) without time zone
);


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    tenant_id uuid,
    job_title character varying(255),
    hourly_rate numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    department_id uuid,
    role character varying(255) DEFAULT 'tecnico'::character varying NOT NULL,
    last_seen_at timestamp(0) without time zone
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: activity_log id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_log ALTER COLUMN id SET DEFAULT nextval('public.activity_log_id_seq'::regclass);


--
-- Name: checklist_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.checklist_templates ALTER COLUMN id SET DEFAULT nextval('public.checklist_templates_id_seq'::regclass);


--
-- Name: companies id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.companies ALTER COLUMN id SET DEFAULT nextval('public.companies_id_seq'::regclass);


--
-- Name: conversation_user id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.conversation_user ALTER COLUMN id SET DEFAULT nextval('public.conversation_user_id_seq'::regclass);


--
-- Name: criticality_histories id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.criticality_histories ALTER COLUMN id SET DEFAULT nextval('public.criticality_histories_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: maintenance_order_delegations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_order_delegations ALTER COLUMN id SET DEFAULT nextval('public.maintenance_order_delegations_id_seq'::regclass);


--
-- Name: media id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media ALTER COLUMN id SET DEFAULT nextval('public.media_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: permissions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions ALTER COLUMN id SET DEFAULT nextval('public.permissions_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: activity_log activity_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_log
    ADD CONSTRAINT activity_log_pkey PRIMARY KEY (id);


--
-- Name: asset_movements asset_movements_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_movements
    ADD CONSTRAINT asset_movements_pkey PRIMARY KEY (id);


--
-- Name: assets assets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.assets
    ADD CONSTRAINT assets_pkey PRIMARY KEY (id);


--
-- Name: assets assets_tag_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.assets
    ADD CONSTRAINT assets_tag_unique UNIQUE (tag);


--
-- Name: attachments attachments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attachments
    ADD CONSTRAINT attachments_pkey PRIMARY KEY (id);


--
-- Name: branches branches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.branches
    ADD CONSTRAINT branches_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: checklist_groups checklist_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.checklist_groups
    ADD CONSTRAINT checklist_groups_pkey PRIMARY KEY (id);


--
-- Name: checklist_templates checklist_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.checklist_templates
    ADD CONSTRAINT checklist_templates_pkey PRIMARY KEY (id);


--
-- Name: clients clients_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clients
    ADD CONSTRAINT clients_pkey PRIMARY KEY (id);


--
-- Name: companies companies_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_pkey PRIMARY KEY (id);


--
-- Name: contracts contracts_contract_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contracts
    ADD CONSTRAINT contracts_contract_number_unique UNIQUE (contract_number);


--
-- Name: contracts contracts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contracts
    ADD CONSTRAINT contracts_pkey PRIMARY KEY (id);


--
-- Name: conversation_user conversation_user_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.conversation_user
    ADD CONSTRAINT conversation_user_pkey PRIMARY KEY (id);


--
-- Name: conversations conversations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.conversations
    ADD CONSTRAINT conversations_pkey PRIMARY KEY (id);


--
-- Name: criticality_histories criticality_histories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.criticality_histories
    ADD CONSTRAINT criticality_histories_pkey PRIMARY KEY (id);


--
-- Name: criticality_levels criticality_levels_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.criticality_levels
    ADD CONSTRAINT criticality_levels_pkey PRIMARY KEY (id);


--
-- Name: departments departments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.departments
    ADD CONSTRAINT departments_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: internal_communications internal_communications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.internal_communications
    ADD CONSTRAINT internal_communications_pkey PRIMARY KEY (id);


--
-- Name: internal_units internal_units_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.internal_units
    ADD CONSTRAINT internal_units_pkey PRIMARY KEY (id);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: locations locations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.locations
    ADD CONSTRAINT locations_pkey PRIMARY KEY (id);


--
-- Name: maintenance_order_checklists maintenance_order_checklists_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_order_checklists
    ADD CONSTRAINT maintenance_order_checklists_pkey PRIMARY KEY (id);


--
-- Name: maintenance_order_delegations maintenance_order_delegations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_order_delegations
    ADD CONSTRAINT maintenance_order_delegations_pkey PRIMARY KEY (id);


--
-- Name: maintenance_order_material maintenance_order_material_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_order_material
    ADD CONSTRAINT maintenance_order_material_pkey PRIMARY KEY (id);


--
-- Name: maintenance_order_materials maintenance_order_materials_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_order_materials
    ADD CONSTRAINT maintenance_order_materials_pkey PRIMARY KEY (id);


--
-- Name: maintenance_orders maintenance_orders_os_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_orders
    ADD CONSTRAINT maintenance_orders_os_number_unique UNIQUE (os_number);


--
-- Name: maintenance_orders maintenance_orders_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_orders
    ADD CONSTRAINT maintenance_orders_pkey PRIMARY KEY (id);


--
-- Name: maintenance_sessions maintenance_sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_sessions
    ADD CONSTRAINT maintenance_sessions_pkey PRIMARY KEY (id);


--
-- Name: materials materials_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.materials
    ADD CONSTRAINT materials_pkey PRIMARY KEY (id);


--
-- Name: materials materials_sku_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.materials
    ADD CONSTRAINT materials_sku_unique UNIQUE (sku);


--
-- Name: media media_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media
    ADD CONSTRAINT media_pkey PRIMARY KEY (id);


--
-- Name: media media_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media
    ADD CONSTRAINT media_uuid_unique UNIQUE (uuid);


--
-- Name: messages messages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messages
    ADD CONSTRAINT messages_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: model_has_permissions model_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_pkey PRIMARY KEY (permission_id, model_id, model_type);


--
-- Name: model_has_roles model_has_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_pkey PRIMARY KEY (role_id, model_id, model_type);


--
-- Name: notifications notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: permissions permissions_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: plans plans_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plans
    ADD CONSTRAINT plans_pkey PRIMARY KEY (id);


--
-- Name: reported_problems reported_problems_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reported_problems
    ADD CONSTRAINT reported_problems_pkey PRIMARY KEY (id);


--
-- Name: role_has_permissions role_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_pkey PRIMARY KEY (permission_id, role_id);


--
-- Name: roles roles_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: stock_items stock_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_items
    ADD CONSTRAINT stock_items_pkey PRIMARY KEY (id);


--
-- Name: tenants tenants_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenants
    ADD CONSTRAINT tenants_pkey PRIMARY KEY (id);


--
-- Name: tenants tenants_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenants
    ADD CONSTRAINT tenants_slug_unique UNIQUE (slug);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: activity_log_log_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_log_log_name_index ON public.activity_log USING btree (log_name);


--
-- Name: attachments_evidence_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX attachments_evidence_type_index ON public.attachments USING btree (evidence_type);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: causer; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX causer ON public.activity_log USING btree (causer_type, causer_id);


--
-- Name: checklist_groups_tenant_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX checklist_groups_tenant_id_index ON public.checklist_groups USING btree (tenant_id);


--
-- Name: checklist_templates_tenant_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX checklist_templates_tenant_id_index ON public.checklist_templates USING btree (tenant_id);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: maintenance_orders_tenant_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX maintenance_orders_tenant_id_index ON public.maintenance_orders USING btree (tenant_id);


--
-- Name: media_model_type_model_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX media_model_type_model_id_index ON public.media USING btree (model_type, model_id);


--
-- Name: media_order_column_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX media_order_column_index ON public.media USING btree (order_column);


--
-- Name: model_has_permissions_model_id_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_permissions_model_id_model_type_index ON public.model_has_permissions USING btree (model_id, model_type);


--
-- Name: model_has_roles_model_id_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_roles_model_id_model_type_index ON public.model_has_roles USING btree (model_id, model_type);


--
-- Name: notifications_notifiable_type_notifiable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notifications_notifiable_type_notifiable_id_index ON public.notifications USING btree (notifiable_type, notifiable_id);


--
-- Name: personal_access_tokens_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_expires_at_index ON public.personal_access_tokens USING btree (expires_at);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: reported_problems_tenant_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reported_problems_tenant_id_index ON public.reported_problems USING btree (tenant_id);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: stock_items_internal_unit_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stock_items_internal_unit_id_index ON public.stock_items USING btree (internal_unit_id);


--
-- Name: stock_items_tenant_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stock_items_tenant_id_index ON public.stock_items USING btree (tenant_id);


--
-- Name: subject; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subject ON public.activity_log USING btree (subject_type, subject_id);


--
-- Name: asset_movements asset_movements_asset_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_movements
    ADD CONSTRAINT asset_movements_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id) ON DELETE CASCADE;


--
-- Name: asset_movements asset_movements_maintenance_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_movements
    ADD CONSTRAINT asset_movements_maintenance_order_id_foreign FOREIGN KEY (maintenance_order_id) REFERENCES public.maintenance_orders(id) ON DELETE SET NULL;


--
-- Name: asset_movements asset_movements_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asset_movements
    ADD CONSTRAINT asset_movements_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: assets assets_checklist_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.assets
    ADD CONSTRAINT assets_checklist_group_id_foreign FOREIGN KEY (checklist_group_id) REFERENCES public.checklist_groups(id) ON DELETE SET NULL;


--
-- Name: assets assets_client_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.assets
    ADD CONSTRAINT assets_client_id_foreign FOREIGN KEY (client_id) REFERENCES public.clients(id) ON DELETE SET NULL;


--
-- Name: assets assets_criticality_level_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.assets
    ADD CONSTRAINT assets_criticality_level_id_foreign FOREIGN KEY (criticality_level_id) REFERENCES public.criticality_levels(id);


--
-- Name: assets assets_current_location_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.assets
    ADD CONSTRAINT assets_current_location_id_foreign FOREIGN KEY (current_location_id) REFERENCES public.locations(id);


--
-- Name: assets assets_internal_unit_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.assets
    ADD CONSTRAINT assets_internal_unit_id_foreign FOREIGN KEY (internal_unit_id) REFERENCES public.internal_units(id) ON DELETE SET NULL;


--
-- Name: attachments attachments_maintenance_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attachments
    ADD CONSTRAINT attachments_maintenance_order_id_foreign FOREIGN KEY (maintenance_order_id) REFERENCES public.maintenance_orders(id) ON DELETE CASCADE;


--
-- Name: attachments attachments_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attachments
    ADD CONSTRAINT attachments_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: branches branches_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.branches
    ADD CONSTRAINT branches_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: clients clients_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clients
    ADD CONSTRAINT clients_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: companies companies_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: contracts contracts_asset_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contracts
    ADD CONSTRAINT contracts_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id);


--
-- Name: contracts contracts_client_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contracts
    ADD CONSTRAINT contracts_client_id_foreign FOREIGN KEY (client_id) REFERENCES public.clients(id);


--
-- Name: contracts contracts_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contracts
    ADD CONSTRAINT contracts_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: conversation_user conversation_user_conversation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.conversation_user
    ADD CONSTRAINT conversation_user_conversation_id_foreign FOREIGN KEY (conversation_id) REFERENCES public.conversations(id) ON DELETE CASCADE;


--
-- Name: conversation_user conversation_user_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.conversation_user
    ADD CONSTRAINT conversation_user_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: conversations conversations_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.conversations
    ADD CONSTRAINT conversations_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: criticality_histories criticality_histories_asset_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.criticality_histories
    ADD CONSTRAINT criticality_histories_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id) ON DELETE CASCADE;


--
-- Name: criticality_histories criticality_histories_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.criticality_histories
    ADD CONSTRAINT criticality_histories_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: criticality_levels criticality_levels_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.criticality_levels
    ADD CONSTRAINT criticality_levels_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: departments departments_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.departments
    ADD CONSTRAINT departments_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: internal_communications internal_communications_maintenance_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.internal_communications
    ADD CONSTRAINT internal_communications_maintenance_order_id_foreign FOREIGN KEY (maintenance_order_id) REFERENCES public.maintenance_orders(id) ON DELETE CASCADE;


--
-- Name: internal_communications internal_communications_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.internal_communications
    ADD CONSTRAINT internal_communications_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: internal_communications internal_communications_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.internal_communications
    ADD CONSTRAINT internal_communications_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: internal_units internal_units_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.internal_units
    ADD CONSTRAINT internal_units_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: locations locations_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.locations
    ADD CONSTRAINT locations_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: maintenance_order_checklists maintenance_order_checklists_department_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_order_checklists
    ADD CONSTRAINT maintenance_order_checklists_department_id_foreign FOREIGN KEY (department_id) REFERENCES public.departments(id) ON DELETE SET NULL;


--
-- Name: maintenance_order_checklists maintenance_order_checklists_maintenance_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_order_checklists
    ADD CONSTRAINT maintenance_order_checklists_maintenance_order_id_foreign FOREIGN KEY (maintenance_order_id) REFERENCES public.maintenance_orders(id) ON DELETE CASCADE;


--
-- Name: maintenance_order_material maintenance_order_material_maintenance_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_order_material
    ADD CONSTRAINT maintenance_order_material_maintenance_order_id_foreign FOREIGN KEY (maintenance_order_id) REFERENCES public.maintenance_orders(id) ON DELETE CASCADE;


--
-- Name: maintenance_order_material maintenance_order_material_material_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_order_material
    ADD CONSTRAINT maintenance_order_material_material_id_foreign FOREIGN KEY (material_id) REFERENCES public.materials(id) ON DELETE CASCADE;


--
-- Name: maintenance_orders maintenance_orders_asset_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_orders
    ADD CONSTRAINT maintenance_orders_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id) ON DELETE CASCADE;


--
-- Name: maintenance_orders maintenance_orders_assigned_technician_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_orders
    ADD CONSTRAINT maintenance_orders_assigned_technician_id_foreign FOREIGN KEY (assigned_technician_id) REFERENCES public.users(id);


--
-- Name: maintenance_orders maintenance_orders_client_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_orders
    ADD CONSTRAINT maintenance_orders_client_id_foreign FOREIGN KEY (client_id) REFERENCES public.clients(id) ON DELETE SET NULL;


--
-- Name: maintenance_orders maintenance_orders_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_orders
    ADD CONSTRAINT maintenance_orders_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: maintenance_orders maintenance_orders_criticality_level_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_orders
    ADD CONSTRAINT maintenance_orders_criticality_level_id_foreign FOREIGN KEY (criticality_level_id) REFERENCES public.criticality_levels(id);


--
-- Name: maintenance_orders maintenance_orders_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_orders
    ADD CONSTRAINT maintenance_orders_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.maintenance_orders(id) ON DELETE CASCADE;


--
-- Name: maintenance_orders maintenance_orders_parent_os_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_orders
    ADD CONSTRAINT maintenance_orders_parent_os_id_foreign FOREIGN KEY (parent_os_id) REFERENCES public.maintenance_orders(id);


--
-- Name: maintenance_orders maintenance_orders_reported_problem_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_orders
    ADD CONSTRAINT maintenance_orders_reported_problem_id_foreign FOREIGN KEY (reported_problem_id) REFERENCES public.reported_problems(id);


--
-- Name: maintenance_orders maintenance_orders_resolved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_orders
    ADD CONSTRAINT maintenance_orders_resolved_by_foreign FOREIGN KEY (resolved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: maintenance_orders maintenance_orders_transport_vehicle_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_orders
    ADD CONSTRAINT maintenance_orders_transport_vehicle_id_foreign FOREIGN KEY (transport_vehicle_id) REFERENCES public.assets(id) ON DELETE SET NULL;


--
-- Name: maintenance_sessions maintenance_sessions_maintenance_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_sessions
    ADD CONSTRAINT maintenance_sessions_maintenance_order_id_foreign FOREIGN KEY (maintenance_order_id) REFERENCES public.maintenance_orders(id) ON DELETE CASCADE;


--
-- Name: maintenance_sessions maintenance_sessions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.maintenance_sessions
    ADD CONSTRAINT maintenance_sessions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- Name: materials materials_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.materials
    ADD CONSTRAINT materials_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- Name: messages messages_conversation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messages
    ADD CONSTRAINT messages_conversation_id_foreign FOREIGN KEY (conversation_id) REFERENCES public.conversations(id) ON DELETE CASCADE;


--
-- Name: messages messages_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messages
    ADD CONSTRAINT messages_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: model_has_permissions model_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: model_has_roles model_has_roles_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: tenants tenants_plan_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenants
    ADD CONSTRAINT tenants_plan_id_foreign FOREIGN KEY (plan_id) REFERENCES public.plans(id) ON DELETE SET NULL;


--
-- Name: users users_department_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_department_id_foreign FOREIGN KEY (department_id) REFERENCES public.departments(id) ON DELETE SET NULL;


--
-- Name: users users_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict GVsKhRyAexZrnISnEPmeImTVfCA1FTPuzwaEIese9QsxTtlxqxdGC3PjVFi7Obe

--
-- PostgreSQL database dump
--

\restrict MaZIbILtrmBRdFyW1u2VGRAUi2BYi4sEnwtyqxsC2HKKgdNMJ6bhN7ToTLLSQpW

-- Dumped from database version 16.14 (Ubuntu 16.14-0ubuntu0.24.04.1)
-- Dumped by pg_dump version 16.14 (Ubuntu 16.14-0ubuntu0.24.04.1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2024_01_01_000000_create_plans_table	1
5	2026_04_23_125000_create_tenants_table	1
6	2026_04_23_125457_add_tenant_id_to_users_table	1
7	2026_04_23_130014_add_job_title_to_users_table	1
8	2026_04_23_133226_create_permission_tables	1
9	2026_04_23_152052_create_personal_access_tokens_table	1
10	2026_04_24_100000_create_locations_table	1
11	2026_04_24_115656_create_assets_table	1
12	2026_04_25_021022_alter_patrimonio_to_nullable_in_assets	1
13	2026_04_25_160304_create_acl_tables	1
14	2026_04_25_174802_add_financial_fields_to_assets_table	1
15	2026_04_25_182917_add_technical_and_financial_fields_to_assets_table	1
16	2026_04_25_185453_create_maintenance_orders_table	1
17	2026_04_25_191341_add_resolution_fields_to_maintenance_orders_table	1
18	2026_04_25_195004_alter_maintenance_orders_for_workflow	1
19	2026_04_25_195509_add_hourly_rate_to_users_table	1
20	2026_04_25_200426_create_materials_table	1
21	2026_04_25_200444_create_maintenance_order_material_table	1
22	2026_04_26_101018_create_departments_table	1
23	2026_04_26_105805_create_maintenance_sessions_table	1
24	2026_04_26_111850_create_maintenance_order_checklists_table	1
25	2026_04_26_121759_add_price_to_materials_table	1
26	2026_04_26_122055_drop_category_column_from_materials	1
27	2026_04_26_123906_add_tenant_id_to_permission_tables	1
28	2026_04_26_232129_add_tenant_id_to_maintenance_orders_table	1
29	2026_04_26_234800_add_missing_media_columns_to_maintenance_orders	1
30	2026_04_27_180218_add_execution_fields_to_maintenance_orders_table	1
31	2026_04_27_225010_add_department_id_and_code_to_maintenance_order_checklists_table	1
32	2026_04_28_002049_add_code_column_to_departments_table	1
33	2026_04_28_003103_add_tenant_id_to_maintenance_order_checklists_table	1
34	2026_04_28_004123_adjust_maintenance_order_id_in_checklists	1
35	2026_04_28_122319_create_checklist_groups_table	1
36	2026_04_28_122321_add_classification_to_maintenance_order_checklists_table	1
37	2026_04_28_125924_add_checklist_group_id_to_assets_table	1
38	2026_04_28_180147_add_hours_and_status_to_maintenance_orders_table	1
39	2026_04_28_182122_make_description_nullable_in_maintenance_orders_table	1
40	2026_04_29_125507_add_tenant_id_to_materials_table	1
41	2026_04_29_135055_add_department_id_and_role_to_users_table	1
42	2026_04_29_225723_add_technical_notes_to_maintenance_orders_table	1
43	2026_04_30_000157_adjust_maintenance_orders_columns	1
44	2026_04_30_001733_fix_columns_for_artifacts_in_maintenance_orders	1
45	2026_04_30_004858_create_media_table	1
46	2026_04_30_011134_fix_media_table_for_uuids	1
47	2026_05_01_003640_create_companies_table	1
48	2026_05_01_163836_add_section_to_maintenance_order_checklists_table	1
49	2026_05_01_164319_add_category_to_maintenance_order_checklists_table	1
50	2026_05_01_201637_create_maintenance_order_materials_table	1
51	2026_05_01_211530_add_number_and_signature_to_maintenance_orders_table	1
52	2026_05_03_021756_add_workflow_columns_to_maintenance_orders	1
53	2026_05_03_032918_add_reasons_to_maintenance_orders	1
54	2026_05_03_034123_create_clients_table	1
55	2026_05_03_034443_add_client_to_maintenance_orders	1
56	2026_05_03_140037_add_maintenance_type_to_orders	1
57	2026_05_03_153517_add_reported_problem_to_maintenance_orders	1
58	2026_05_03_220802_altera_tipo_technician_id_em_maintenance_orders	1
59	2026_05_03_223111_add_activity_type_to_clients_table	1
60	2026_05_03_224937_add_spec_and_year_to_assets_table	1
61	2026_05_03_233525_add_client_id_to_assets_table	1
62	2026_05_03_235140_add_hourly_rate_to_users_table	1
63	2026_05_04_001058_add_logistics_automation_fields	1
64	2026_05_04_002010_create_internal_units_table	1
65	2026_05_04_002439_add_internal_unit_id_to_assets_table	1
66	2026_05_04_003056_create_asset_movements_table	1
67	2026_05_04_005008_add_financial_fields_to_maintenance_orders_table	1
68	2026_05_04_010214_add_geoloc_fields_to_internal_units_table	1
69	2026_05_04_010301_add_geoloc_fields_to_clients_table	2
70	2026_05_04_012141_create_stock_items_table	2
71	2026_05_04_012400_create_stock_movements_table	2
72	2026_05_04_013431_create_activity_log_table	2
73	2026_05_04_021744_alter_activity_log_subject_id_to_uuid	2
74	2026_05_04_110409_create_internal_communications_table	2
75	2026_05_04_115406_add_unit_price_to_maintenance_order_materials_table	2
76	2026_05_04_122226_fix_activity_log_subject_id_type	2
77	2026_05_04_122448_fix_activity_log_subject_id_column	2
78	2026_05_04_130516_add_geo_columns_to_assets	2
79	2026_05_04_130811_add_geo_columns_to_assets	2
80	2026_05_04_224237_create_contracts_table	2
81	2026_05_04_224911_add_soft_deletes_to_contracts_table	1
82	2026_05_04_225204_add_tenant_id_to_contracts_table	1
83	2026_05_04_232911_adjust_assets_for_capacity_and_tag	3
84	2026_05_04_234027_create_criticality_levels_table	3
85	2026_05_04_234028_create_criticality_histories_table	3
86	2026_05_04_234029_add_criticality_level_to_assets	3
87	2026_05_04_235735_add_criticality_to_maintenance_orders	3
88	2026_05_05_000725_add_rework_to_maintenance_orders	3
89	2026_05_05_010559_update_maintenance_orders_for_workshop_control	3
90	2026_05_05_015239_create_maintenance_order_delegations_table	3
91	2026_05_05_120752_create_attachments_table	3
92	2026_05_06_165503_add_address_to_attachments_table	3
93	2026_05_06_183148_create_branches_table	3
94	2026_05_06_194359_add_contract_number_fix	3
95	2026_05_06_194621_sync_contracts_table_columns	3
96	2026_05_06_194806_force_sync_contracts_columns	3
97	2026_05_06_195022_add_missing_columns_to_contracts_final	3
98	2026_05_06_195301_repair_contracts_table_final	3
99	2026_05_06_195534_change_contracts_id_to_uuid	3
100	2026_05_07_015755_add_saas_metrics_to_tenants_table	3
101	2026_05_07_110743_add_plan_fields_to_tenants_table	3
102	2026_05_07_114310_add_tenant_id_to_checklist_groups_table	3
103	2026_05_07_120029_add_tenant_id_to_companies_table	3
104	2026_05_07_130651_add_plan_id_to_tenants_table	3
105	2026_05_08_170939_create_checklist_templates_table	3
106	2026_05_11_145246_add_horimetro_roi_to_assets_table	1
107	2026_05_08_171341_add_tenant_id_to_checklist_templates_table	1
108	2026_05_08_174009_add_asset_category_id_to_assets_table	1
109	2026_05_09_195200_add_checklist_to_assets_table	1
110	2026_05_09_161609_adjust_assets_table_to_static	1
111	2026_05_09_195842_create_asset_logs_table	1
112	2026_05_12_000000_create_chat_system_tables	4
113	2026_05_12_000001_create_notifications_table	4
114	2026_05_12_174800_add_soft_deletes_to_material_categories_table	1
115	2026_05_12_180121_create_conversations_table	1
116	2026_05_12_180121_create_messages_table	1
117	2026_05_12_180122_create_conversation_user_table	1
118	2026_05_12_180647_create_notifications_table	1
119	2026_05_12_200633_create_command_center_tables	1
120	2026_05_12_202622_create_order_messages_table	1
121	2026_05_12_203030_create_fleet_statuses_table	1
122	2026_05_12_230632_create_command_center_tables	1
123	2026_05_14_104816_add_tenant_id_to_fleet_status_table	1
124	2026_05_14_105131_create_parts_requests_table	1
125	2026_05_14_112545_add_tenant_id_to_fleet_statuses_table	1
126	2026_05_14_124949_add_erp_fields_to_clients_table	1
127	2026_05_14_125046_add_erp_fields_to_clients_table	1
128	2026_05_14_134000_create_chat_rooms_table	1
129	2026_05_14_134100_create_chat_room_user_table	1
130	2026_05_14_162611_add_department_id_to_roles_table	1
131	2026_05_14_164653_add_description_to_departments_table	1
132	2026_05_14_171935_add_maintenance_order_id_to_chat_rooms_table	1
133	2026_05_14_173658_add_chat_room_id_to_order_messages_table	1
134	2026_05_14_175044_add_dossier_fields_to_maintenance_orders_table	1
135	2026_05_16_112439_create_chat_messages_table	1
136	2026_05_16_132355_create_material_requests_system_tables	1
137	2026_05_18_140000_add_tenant_id_to_permissions_pivots	1
138	2026_05_18_180157_add_tenant_id_to_model_has_permissions_table	1
139	2026_05_18_184605_alter_tenant_id_to_uuid_in_permission_tables	1
140	2026_05_18_184834_alter_spatie_tenant_id_to_uuid	1
141	2026_05_18_194431_add_tenant_id_to_model_has_permissions_table	1
142	2026_05_18_194717_add_tenant_id_to_model_has_permissions_table	1
143	2026_05_18_195040_add_tenant_id_to_spatie_pivot_tables	1
144	2026_05_19_000000_normalize_permissions_for_multitenancy	1
145	2026_05_19_223134_add_signatures_to_maintenance_orders_table	1
146	2026_05_19_224028_create_maintenance_plans_table	1
147	2026_05_21_115957_add_asaas_fields_to_tenants_table	1
148	2026_05_21_115957_create_invoices_table	1
149	2026_05_21_162923_add_status_to_maintenance_order_materials_table	1
150	2026_05_21_214231_create_maintenance_status_histories_table	1
151	2026_05_22_000001_create_suppliers_table	1
152	2026_05_22_000002_create_purchase_tables	1
153	2026_05_22_000003_create_accounts_payable_table	1
154	2026_05_22_000004_create_suppliers_table_sql	1
155	2026_05_22_091741_add_price_fields_to_plans_table	1
156	2026_05_22_105837_add_temp_password_to_users_table	1
157	2026_05_22_152553_change_default_role_in_users_table	1
158	2026_05_25_152217_add_features_to_tenants_table	1
159	2026_05_26_131042_fix_model_has_roles_tenant_id_type	1
160	2026_05_26_140221_add_level_to_plans_table	1
161	2026_05_28_170459_create_account_payables_table	1
162	2026_06_01_144348_add_tenant_id_to_maintenance_status_histories_table	1
163	2026_06_03_084446_add_description_to_material_categories_table	1
164	2026_05_12_180121_create_messages_table	1
165	2026_06_03_085019_add_category_id_to_materials_table	1
166	2026_06_03_085312_add_material_category_id_to_materials_table	1
167	2026_06_03_102643_expand_attributes_in_materials_table	1
168	2026_06_03_150444_create_bill_categories_table	1
169	2026_06_03_172913_add_criticality_to_maintenance_orders_table	1
170	2026_06_03_224729_add_branch_id_to_account_payables_table	1
171	2026_06_03_225404_create_cost_centers_table	1
172	2026_06_03_233708_fix_account_payables_columns	1
173	2026_06_03_234035_fix_account_payables_table	1
174	2026_06_04_000000_fix_all_missing_columns	1
175	2026_06_04_175637_consolidate_database_schema	1
176	2026_06_04_191404_add_tenant_id_to_notifications_table	1
177	2026_06_04_210000_add_last_seen_at_to_users_table	5
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 177, true);


--
-- PostgreSQL database dump complete
--

\unrestrict MaZIbILtrmBRdFyW1u2VGRAUi2BYi4sEnwtyqxsC2HKKgdNMJ6bhN7ToTLLSQpW

