--
-- PostgreSQL database dump
--

\restrict termovaultschemasnapshot

-- Dumped from database version 16.14
-- Dumped by pg_dump version 16.14

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

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: archivos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.archivos (
    id bigint NOT NULL,
    inspeccion_id bigint NOT NULL,
    tipo character varying(30) NOT NULL,
    nombre_original character varying(255),
    s3_bucket character varying(100) NOT NULL,
    s3_key character varying(500) NOT NULL,
    tamano_bytes bigint,
    mime_type character varying(100),
    subido_por bigint,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT chk_archivos_s3_bucket_valido CHECK (((s3_bucket)::text <> 'local'::text))
);


--
-- Name: archivos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.archivos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: archivos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.archivos_id_seq OWNED BY public.archivos.id;


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
-- Name: criticidades; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.criticidades (
    id bigint NOT NULL,
    nivel integer NOT NULL,
    nombre character varying(30) NOT NULL,
    color character varying(7)
);


--
-- Name: criticidades_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.criticidades_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: criticidades_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.criticidades_id_seq OWNED BY public.criticidades.id;


--
-- Name: elementos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.elementos (
    id bigint NOT NULL,
    yacimiento_id bigint NOT NULL,
    tipo_elemento_id bigint NOT NULL,
    funcion character varying(20),
    nivel_tension_id bigint,
    nombre character varying(150) NOT NULL,
    codigo character varying(50) NOT NULL,
    marca character varying(100),
    modelo character varying(100),
    n_serie character varying(100),
    criticidad_id bigint,
    estado_operativo character varying(30) DEFAULT 'operativo'::character varying NOT NULL,
    observaciones_generales text,
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    created_by bigint,
    updated_by bigint
);


--
-- Name: elementos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.elementos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: elementos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.elementos_id_seq OWNED BY public.elementos.id;


--
-- Name: empresas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.empresas (
    id bigint NOT NULL,
    nombre character varying(150) NOT NULL,
    plan character varying(30) DEFAULT 'basico'::character varying NOT NULL,
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: empresas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.empresas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: empresas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.empresas_id_seq OWNED BY public.empresas.id;


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
-- Name: inspecciones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inspecciones (
    id bigint NOT NULL,
    elemento_id bigint NOT NULL,
    tecnico_id bigint NOT NULL,
    fecha_inspeccion timestamp(0) without time zone NOT NULL,
    cuadrilla character varying(100),
    integrantes text,
    empresa_contratista character varying(150),
    condiciones_clima character varying(50),
    resumen text,
    estado character varying(20) DEFAULT 'enviada'::character varying NOT NULL,
    revisada_por bigint,
    fecha_revision timestamp(0) without time zone,
    observaciones_revisor text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    created_by bigint,
    updated_by bigint,
    cerrada_por bigint,
    fecha_cierre timestamp(0) without time zone,
    CONSTRAINT chk_inspecciones_estado CHECK (((estado)::text = ANY ((ARRAY['enviada'::character varying, 'revisada'::character varying, 'cerrada'::character varying])::text[])))
);


--
-- Name: inspecciones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.inspecciones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inspecciones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inspecciones_id_seq OWNED BY public.inspecciones.id;


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
-- Name: niveles_tension; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.niveles_tension (
    id bigint NOT NULL,
    kv numeric(6,2) NOT NULL,
    etiqueta character varying(20) NOT NULL,
    activo boolean DEFAULT true NOT NULL
);


--
-- Name: niveles_tension_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.niveles_tension_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: niveles_tension_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.niveles_tension_id_seq OWNED BY public.niveles_tension.id;


--
-- Name: novedades; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.novedades (
    id bigint NOT NULL,
    inspeccion_id bigint NOT NULL,
    criticidad_id bigint,
    titulo character varying(200) NOT NULL,
    descripcion text,
    ubicacion_dentro_elemento character varying(200),
    temperatura_detectada numeric(5,2),
    accion_recomendada text,
    estado character varying(20) DEFAULT 'abierta'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT chk_novedades_estado CHECK (((estado)::text = ANY ((ARRAY['abierta'::character varying, 'resuelta'::character varying])::text[])))
);


--
-- Name: novedades_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.novedades_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: novedades_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.novedades_id_seq OWNED BY public.novedades.id;


--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.roles (
    id bigint NOT NULL,
    codigo character varying(30) NOT NULL,
    nombre character varying(50) NOT NULL
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
-- Name: tipos_elemento; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tipos_elemento (
    id bigint NOT NULL,
    codigo character varying(50) NOT NULL,
    nombre character varying(100) NOT NULL,
    prefijo_archivo character varying(20),
    requiere_tension boolean DEFAULT false NOT NULL,
    activo boolean DEFAULT true NOT NULL
);


--
-- Name: tipos_elemento_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tipos_elemento_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tipos_elemento_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tipos_elemento_id_seq OWNED BY public.tipos_elemento.id;


--
-- Name: usuario_yacimientos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.usuario_yacimientos (
    usuario_id bigint NOT NULL,
    yacimiento_id bigint NOT NULL
);


--
-- Name: usuarios; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.usuarios (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    rol_id bigint NOT NULL,
    nombre character varying(100) NOT NULL,
    apellido character varying(100) NOT NULL,
    email character varying(150) NOT NULL,
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: usuarios_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.usuarios_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: usuarios_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.usuarios_id_seq OWNED BY public.usuarios.id;


--
-- Name: yacimientos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.yacimientos (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    nombre character varying(150) NOT NULL,
    codigo character varying(50) NOT NULL,
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: yacimientos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.yacimientos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: yacimientos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.yacimientos_id_seq OWNED BY public.yacimientos.id;


--
-- Name: archivos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.archivos ALTER COLUMN id SET DEFAULT nextval('public.archivos_id_seq'::regclass);


--
-- Name: criticidades id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.criticidades ALTER COLUMN id SET DEFAULT nextval('public.criticidades_id_seq'::regclass);


--
-- Name: elementos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.elementos ALTER COLUMN id SET DEFAULT nextval('public.elementos_id_seq'::regclass);


--
-- Name: empresas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresas ALTER COLUMN id SET DEFAULT nextval('public.empresas_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: inspecciones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inspecciones ALTER COLUMN id SET DEFAULT nextval('public.inspecciones_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: niveles_tension id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.niveles_tension ALTER COLUMN id SET DEFAULT nextval('public.niveles_tension_id_seq'::regclass);


--
-- Name: novedades id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.novedades ALTER COLUMN id SET DEFAULT nextval('public.novedades_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: tipos_elemento id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tipos_elemento ALTER COLUMN id SET DEFAULT nextval('public.tipos_elemento_id_seq'::regclass);


--
-- Name: usuarios id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios ALTER COLUMN id SET DEFAULT nextval('public.usuarios_id_seq'::regclass);


--
-- Name: yacimientos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yacimientos ALTER COLUMN id SET DEFAULT nextval('public.yacimientos_id_seq'::regclass);


--
-- Name: archivos archivos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.archivos
    ADD CONSTRAINT archivos_pkey PRIMARY KEY (id);


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
-- Name: criticidades criticidades_nivel_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.criticidades
    ADD CONSTRAINT criticidades_nivel_unique UNIQUE (nivel);


--
-- Name: criticidades criticidades_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.criticidades
    ADD CONSTRAINT criticidades_pkey PRIMARY KEY (id);


--
-- Name: elementos elementos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.elementos
    ADD CONSTRAINT elementos_pkey PRIMARY KEY (id);


--
-- Name: elementos elementos_yacimiento_id_codigo_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.elementos
    ADD CONSTRAINT elementos_yacimiento_id_codigo_unique UNIQUE (yacimiento_id, codigo);


--
-- Name: empresas empresas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresas
    ADD CONSTRAINT empresas_pkey PRIMARY KEY (id);


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
-- Name: inspecciones inspecciones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inspecciones
    ADD CONSTRAINT inspecciones_pkey PRIMARY KEY (id);


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
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: niveles_tension niveles_tension_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.niveles_tension
    ADD CONSTRAINT niveles_tension_pkey PRIMARY KEY (id);


--
-- Name: novedades novedades_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.novedades
    ADD CONSTRAINT novedades_pkey PRIMARY KEY (id);


--
-- Name: roles roles_codigo_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_codigo_unique UNIQUE (codigo);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: tipos_elemento tipos_elemento_codigo_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tipos_elemento
    ADD CONSTRAINT tipos_elemento_codigo_unique UNIQUE (codigo);


--
-- Name: tipos_elemento tipos_elemento_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tipos_elemento
    ADD CONSTRAINT tipos_elemento_pkey PRIMARY KEY (id);


--
-- Name: usuario_yacimientos usuario_yacimientos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuario_yacimientos
    ADD CONSTRAINT usuario_yacimientos_pkey PRIMARY KEY (usuario_id, yacimiento_id);


--
-- Name: usuarios usuarios_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_email_unique UNIQUE (email);


--
-- Name: usuarios usuarios_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_pkey PRIMARY KEY (id);


--
-- Name: yacimientos yacimientos_empresa_id_codigo_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yacimientos
    ADD CONSTRAINT yacimientos_empresa_id_codigo_unique UNIQUE (empresa_id, codigo);


--
-- Name: yacimientos yacimientos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yacimientos
    ADD CONSTRAINT yacimientos_pkey PRIMARY KEY (id);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: idx_archivos_inspeccion; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_archivos_inspeccion ON public.archivos USING btree (inspeccion_id);


--
-- Name: idx_elementos_funcion; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_elementos_funcion ON public.elementos USING btree (funcion);


--
-- Name: idx_elementos_tension; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_elementos_tension ON public.elementos USING btree (nivel_tension_id);


--
-- Name: idx_elementos_tipo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_elementos_tipo ON public.elementos USING btree (tipo_elemento_id);


--
-- Name: idx_elementos_yacimiento; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_elementos_yacimiento ON public.elementos USING btree (yacimiento_id);


--
-- Name: idx_elementos_yacimiento_tipo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_elementos_yacimiento_tipo ON public.elementos USING btree (yacimiento_id, tipo_elemento_id);


--
-- Name: idx_inspecciones_elemento; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_inspecciones_elemento ON public.inspecciones USING btree (elemento_id);


--
-- Name: idx_inspecciones_estado_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_inspecciones_estado_fecha ON public.inspecciones USING btree (estado, fecha_inspeccion);


--
-- Name: idx_inspecciones_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_inspecciones_fecha ON public.inspecciones USING btree (fecha_inspeccion);


--
-- Name: idx_inspecciones_tecnico; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_inspecciones_tecnico ON public.inspecciones USING btree (tecnico_id);


--
-- Name: idx_inspecciones_tecnico_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_inspecciones_tecnico_fecha ON public.inspecciones USING btree (tecnico_id, fecha_inspeccion);


--
-- Name: idx_novedades_criticidad; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_novedades_criticidad ON public.novedades USING btree (criticidad_id);


--
-- Name: idx_novedades_estado; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_novedades_estado ON public.novedades USING btree (estado);


--
-- Name: idx_novedades_inspeccion; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_novedades_inspeccion ON public.novedades USING btree (inspeccion_id);


--
-- Name: idx_novedades_inspeccion_criticidad; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_novedades_inspeccion_criticidad ON public.novedades USING btree (inspeccion_id, criticidad_id);


--
-- Name: idx_usuarios_email_lower_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_usuarios_email_lower_unique ON public.usuarios USING btree (lower((email)::text));


--
-- Name: idx_usuarios_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_usuarios_empresa ON public.usuarios USING btree (empresa_id);


--
-- Name: idx_yacimientos_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_yacimientos_empresa ON public.yacimientos USING btree (empresa_id);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: archivos archivos_inspeccion_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.archivos
    ADD CONSTRAINT archivos_inspeccion_id_foreign FOREIGN KEY (inspeccion_id) REFERENCES public.inspecciones(id) ON DELETE CASCADE;


--
-- Name: archivos archivos_subido_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.archivos
    ADD CONSTRAINT archivos_subido_por_foreign FOREIGN KEY (subido_por) REFERENCES public.usuarios(id);


--
-- Name: elementos elementos_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.elementos
    ADD CONSTRAINT elementos_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.usuarios(id) ON DELETE SET NULL;


--
-- Name: elementos elementos_criticidad_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.elementos
    ADD CONSTRAINT elementos_criticidad_id_foreign FOREIGN KEY (criticidad_id) REFERENCES public.criticidades(id);


--
-- Name: elementos elementos_nivel_tension_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.elementos
    ADD CONSTRAINT elementos_nivel_tension_id_foreign FOREIGN KEY (nivel_tension_id) REFERENCES public.niveles_tension(id);


--
-- Name: elementos elementos_tipo_elemento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.elementos
    ADD CONSTRAINT elementos_tipo_elemento_id_foreign FOREIGN KEY (tipo_elemento_id) REFERENCES public.tipos_elemento(id);


--
-- Name: elementos elementos_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.elementos
    ADD CONSTRAINT elementos_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.usuarios(id) ON DELETE SET NULL;


--
-- Name: elementos elementos_yacimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.elementos
    ADD CONSTRAINT elementos_yacimiento_id_foreign FOREIGN KEY (yacimiento_id) REFERENCES public.yacimientos(id) ON DELETE RESTRICT;


--
-- Name: inspecciones inspecciones_cerrada_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inspecciones
    ADD CONSTRAINT inspecciones_cerrada_por_foreign FOREIGN KEY (cerrada_por) REFERENCES public.usuarios(id) ON DELETE SET NULL;


--
-- Name: inspecciones inspecciones_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inspecciones
    ADD CONSTRAINT inspecciones_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.usuarios(id) ON DELETE SET NULL;


--
-- Name: inspecciones inspecciones_elemento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inspecciones
    ADD CONSTRAINT inspecciones_elemento_id_foreign FOREIGN KEY (elemento_id) REFERENCES public.elementos(id) ON DELETE RESTRICT;


--
-- Name: inspecciones inspecciones_revisada_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inspecciones
    ADD CONSTRAINT inspecciones_revisada_por_foreign FOREIGN KEY (revisada_por) REFERENCES public.usuarios(id);


--
-- Name: inspecciones inspecciones_tecnico_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inspecciones
    ADD CONSTRAINT inspecciones_tecnico_id_foreign FOREIGN KEY (tecnico_id) REFERENCES public.usuarios(id);


--
-- Name: inspecciones inspecciones_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inspecciones
    ADD CONSTRAINT inspecciones_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.usuarios(id) ON DELETE SET NULL;


--
-- Name: novedades novedades_criticidad_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.novedades
    ADD CONSTRAINT novedades_criticidad_id_foreign FOREIGN KEY (criticidad_id) REFERENCES public.criticidades(id);


--
-- Name: novedades novedades_inspeccion_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.novedades
    ADD CONSTRAINT novedades_inspeccion_id_foreign FOREIGN KEY (inspeccion_id) REFERENCES public.inspecciones(id) ON DELETE CASCADE;


--
-- Name: usuario_yacimientos usuario_yacimientos_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuario_yacimientos
    ADD CONSTRAINT usuario_yacimientos_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE CASCADE;


--
-- Name: usuario_yacimientos usuario_yacimientos_yacimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuario_yacimientos
    ADD CONSTRAINT usuario_yacimientos_yacimiento_id_foreign FOREIGN KEY (yacimiento_id) REFERENCES public.yacimientos(id) ON DELETE CASCADE;


--
-- Name: usuarios usuarios_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE RESTRICT;


--
-- Name: usuarios usuarios_rol_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_rol_id_foreign FOREIGN KEY (rol_id) REFERENCES public.roles(id);


--
-- Name: yacimientos yacimientos_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.yacimientos
    ADD CONSTRAINT yacimientos_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE RESTRICT;


--
-- PostgreSQL database dump complete
--

\unrestrict termovaultschemasnapshot
