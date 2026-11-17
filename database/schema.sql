--
-- PostgreSQL database dump
--


-- Dumped from database version 18.6 (Ubuntu 18.6-0ubuntu0.26.04.1)
-- Dumped by pg_dump version 18.6 (Ubuntu 18.6-0ubuntu0.26.04.1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: generar_codigo_ticket(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.generar_codigo_ticket() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.codigo := 'TK-' || LPAD(NEW.id_ticket::TEXT, 6, '0');
    RETURN NEW;
END;
$$;


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: areas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.areas (
    id_area integer NOT NULL,
    nombre character varying(100) NOT NULL,
    descripcion character varying(255),
    activo boolean DEFAULT true,
    id_jefe integer,
    ubicacion character varying(150),
    detalle text
);


--
-- Name: areas_id_area_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.areas_id_area_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: areas_id_area_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.areas_id_area_seq OWNED BY public.areas.id_area;


--
-- Name: prioridades; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.prioridades (
    id_prioridad integer NOT NULL,
    nombre character varying(50) NOT NULL,
    nivel integer NOT NULL,
    color character varying(20)
);


--
-- Name: tickets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tickets (
    id_ticket integer NOT NULL,
    codigo character varying(20),
    titulo character varying(150) NOT NULL,
    descripcion text,
    id_solicitante integer NOT NULL,
    id_categoria integer,
    id_prioridad integer,
    id_area integer,
    id_ubicacion integer,
    id_tecnico integer,
    estado character varying(20) DEFAULT 'pendiente'::character varying NOT NULL,
    solucion text,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_asignacion timestamp without time zone,
    fecha_resolucion timestamp without time zone,
    fecha_cierre timestamp without time zone,
    diagnostico text,
    recomendacion text,
    CONSTRAINT tickets_estado_check CHECK (((estado)::text = ANY (ARRAY[('pendiente'::character varying)::text, ('en_atencion'::character varying)::text, ('completado'::character varying)::text, ('cancelado'::character varying)::text, ('rechazado'::character varying)::text])))
);


--
-- Name: usuarios; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.usuarios (
    id_usuario integer NOT NULL,
    nombres character varying(100) NOT NULL,
    apellidos character varying(100) NOT NULL,
    dni character varying(15),
    correo character varying(150),
    usuario character varying(50) NOT NULL,
    password character varying(255) NOT NULL,
    rol character varying(20) DEFAULT 'solicitante'::character varying NOT NULL,
    id_area integer,
    telefono character varying(20),
    activo boolean DEFAULT true,
    fecha_registro timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT usuarios_rol_check CHECK (((rol)::text = ANY (ARRAY[('solicitante'::character varying)::text, ('tecnico'::character varying)::text, ('admin'::character varying)::text])))
);


--
-- Name: avisos_tickets; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.avisos_tickets AS
 SELECT t.id_ticket,
    t.codigo,
    t.titulo,
    t.estado,
    t.id_tecnico,
    t.fecha_creacion,
    t.fecha_asignacion,
    t.fecha_cierre,
    COALESCE((to_jsonb(a.*) ->> 'nombre'::text), (to_jsonb(a.*) ->> 'nombre_area'::text), (to_jsonb(a.*) ->> 'descripcion'::text)) AS area,
    COALESCE((to_jsonb(p.*) ->> 'nombre'::text), (to_jsonb(p.*) ->> 'descripcion'::text), (to_jsonb(p.*) ->> 'nivel'::text)) AS prioridad,
    COALESCE((to_jsonb(u.*) ->> 'nombre_completo'::text), (to_jsonb(u.*) ->> 'nombres'::text), (to_jsonb(u.*) ->> 'nombre'::text), (to_jsonb(u.*) ->> 'usuario'::text)) AS tecnico
   FROM (((public.tickets t
     LEFT JOIN public.areas a ON ((a.id_area = t.id_area)))
     LEFT JOIN public.prioridades p ON ((p.id_prioridad = t.id_prioridad)))
     LEFT JOIN public.usuarios u ON ((u.id_usuario = t.id_tecnico)));


--
-- Name: categorias; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.categorias (
    id_categoria integer NOT NULL,
    nombre character varying(100) NOT NULL,
    descripcion character varying(255),
    activo boolean DEFAULT true
);


--
-- Name: categorias_id_categoria_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.categorias_id_categoria_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: categorias_id_categoria_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.categorias_id_categoria_seq OWNED BY public.categorias.id_categoria;


--
-- Name: equipo_almacenamiento; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.equipo_almacenamiento (
    id_almacenamiento integer NOT NULL,
    id_equipo integer NOT NULL,
    tipo character varying(40),
    capacidad character varying(40),
    interfaz character varying(40),
    orden integer DEFAULT 0 NOT NULL
);


--
-- Name: equipo_almacenamiento_id_almacenamiento_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.equipo_almacenamiento_id_almacenamiento_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: equipo_almacenamiento_id_almacenamiento_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.equipo_almacenamiento_id_almacenamiento_seq OWNED BY public.equipo_almacenamiento.id_almacenamiento;


--
-- Name: equipo_gpu; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.equipo_gpu (
    id_gpu integer NOT NULL,
    id_equipo integer NOT NULL,
    marca character varying(60),
    modelo character varying(60),
    capacidad character varying(40),
    tipo character varying(40),
    orden integer DEFAULT 0 NOT NULL
);


--
-- Name: equipo_gpu_id_gpu_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.equipo_gpu_id_gpu_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: equipo_gpu_id_gpu_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.equipo_gpu_id_gpu_seq OWNED BY public.equipo_gpu.id_gpu;


--
-- Name: equipo_mantenimiento_componentes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.equipo_mantenimiento_componentes (
    id_componente integer NOT NULL,
    id_mantenimiento integer NOT NULL,
    categoria character varying(30) DEFAULT 'otro'::character varying NOT NULL,
    componente character varying(220) NOT NULL,
    estado character varying(20) DEFAULT 'bueno'::character varying NOT NULL,
    descripcion text,
    orden integer DEFAULT 0 NOT NULL,
    CONSTRAINT equipo_mantenimiento_componentes_categoria_check CHECK (((categoria)::text = ANY ((ARRAY['procesador'::character varying, 'ram'::character varying, 'almacenamiento'::character varying, 'grafico'::character varying, 'so'::character varying, 'red'::character varying, 'otro'::character varying])::text[]))),
    CONSTRAINT equipo_mantenimiento_componentes_estado_check CHECK (((estado)::text = ANY ((ARRAY['bueno'::character varying, 'regular'::character varying, 'danado'::character varying])::text[])))
);


--
-- Name: equipo_mantenimiento_componentes_id_componente_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.equipo_mantenimiento_componentes_id_componente_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: equipo_mantenimiento_componentes_id_componente_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.equipo_mantenimiento_componentes_id_componente_seq OWNED BY public.equipo_mantenimiento_componentes.id_componente;


--
-- Name: equipo_mantenimiento_evidencias; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.equipo_mantenimiento_evidencias (
    id_evidencia integer NOT NULL,
    id_mantenimiento integer NOT NULL,
    id_usuario integer,
    ruta_archivo character varying(255) NOT NULL,
    tipo_archivo character varying(20) NOT NULL,
    fecha_subida timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: equipo_mantenimiento_evidencias_id_evidencia_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.equipo_mantenimiento_evidencias_id_evidencia_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: equipo_mantenimiento_evidencias_id_evidencia_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.equipo_mantenimiento_evidencias_id_evidencia_seq OWNED BY public.equipo_mantenimiento_evidencias.id_evidencia;


--
-- Name: equipo_mantenimiento_ficha; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.equipo_mantenimiento_ficha (
    id_mantenimiento integer NOT NULL,
    codigo_ficha character varying(40),
    asunto text,
    eq_trabajo character varying(150),
    subequipo character varying(150),
    fecha_bios date,
    conformidad_responsable character varying(150),
    conformidad_tecnico character varying(150),
    actualizado_en timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: equipo_mantenimientos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.equipo_mantenimientos (
    id_mantenimiento integer NOT NULL,
    id_equipo integer NOT NULL,
    tipo character varying(20) NOT NULL,
    estado character varying(20) NOT NULL,
    fecha_mantenimiento date NOT NULL,
    descripcion text,
    id_tecnico integer,
    creado_en timestamp without time zone DEFAULT now() NOT NULL,
    partes text,
    CONSTRAINT equipo_mantenimientos_estado_check CHECK (((estado)::text = ANY (ARRAY[('operativo'::character varying)::text, ('mantenimiento'::character varying)::text, ('malogrado'::character varying)::text]))),
    CONSTRAINT equipo_mantenimientos_tipo_check CHECK (((tipo)::text = ANY (ARRAY[('preventivo'::character varying)::text, ('correctivo'::character varying)::text])))
);


--
-- Name: equipo_mantenimientos_id_mantenimiento_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.equipo_mantenimientos_id_mantenimiento_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: equipo_mantenimientos_id_mantenimiento_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.equipo_mantenimientos_id_mantenimiento_seq OWNED BY public.equipo_mantenimientos.id_mantenimiento;


--
-- Name: equipo_ram; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.equipo_ram (
    id_ram integer NOT NULL,
    id_equipo integer NOT NULL,
    capacidad character varying(40),
    tipo character varying(40),
    frecuencia character varying(40),
    orden integer DEFAULT 0 NOT NULL
);


--
-- Name: equipo_ram_id_ram_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.equipo_ram_id_ram_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: equipo_ram_id_ram_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.equipo_ram_id_ram_seq OWNED BY public.equipo_ram.id_ram;


--
-- Name: equipos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.equipos (
    id_equipo integer NOT NULL,
    nombre character varying(100) NOT NULL,
    tipo character varying(50) NOT NULL,
    marca character varying(80) NOT NULL,
    modelo character varying(80),
    numero_serie character varying(100),
    id_area integer,
    id_responsable integer,
    procesador character varying(120),
    sistema_operativo character varying(80),
    mac_lan character varying(40),
    mac_wifi character varying(40),
    observaciones text,
    activo boolean DEFAULT true NOT NULL,
    creado_en timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: equipos_id_equipo_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.equipos_id_equipo_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: equipos_id_equipo_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.equipos_id_equipo_seq OWNED BY public.equipos.id_equipo;


--
-- Name: partes_catalogo; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.partes_catalogo (
    id_parte integer NOT NULL,
    nombre character varying(150) NOT NULL,
    creado_por integer,
    creado_en timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: partes_catalogo_id_parte_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.partes_catalogo_id_parte_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: partes_catalogo_id_parte_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.partes_catalogo_id_parte_seq OWNED BY public.partes_catalogo.id_parte;


--
-- Name: prioridades_id_prioridad_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.prioridades_id_prioridad_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: prioridades_id_prioridad_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.prioridades_id_prioridad_seq OWNED BY public.prioridades.id_prioridad;


--
-- Name: ticket_evidencias; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ticket_evidencias (
    id_evidencia integer NOT NULL,
    id_ticket integer NOT NULL,
    id_usuario integer,
    ruta_archivo text NOT NULL,
    fecha_subida timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: ticket_evidencias_id_evidencia_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ticket_evidencias_id_evidencia_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ticket_evidencias_id_evidencia_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ticket_evidencias_id_evidencia_seq OWNED BY public.ticket_evidencias.id_evidencia;


--
-- Name: ticket_seguimiento; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ticket_seguimiento (
    id_seguimiento integer NOT NULL,
    id_ticket integer NOT NULL,
    id_usuario integer NOT NULL,
    comentario text NOT NULL,
    estado_nuevo character varying(20),
    fecha timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: ticket_seguimiento_id_seguimiento_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ticket_seguimiento_id_seguimiento_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ticket_seguimiento_id_seguimiento_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ticket_seguimiento_id_seguimiento_seq OWNED BY public.ticket_seguimiento.id_seguimiento;


--
-- Name: tickets_id_ticket_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tickets_id_ticket_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tickets_id_ticket_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tickets_id_ticket_seq OWNED BY public.tickets.id_ticket;


--
-- Name: ubicaciones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ubicaciones (
    id_ubicacion integer NOT NULL,
    nombre character varying(100) NOT NULL,
    detalle character varying(255),
    activo boolean DEFAULT true
);


--
-- Name: ubicaciones_id_ubicacion_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ubicaciones_id_ubicacion_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ubicaciones_id_ubicacion_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ubicaciones_id_ubicacion_seq OWNED BY public.ubicaciones.id_ubicacion;


--
-- Name: usuarios_id_usuario_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.usuarios_id_usuario_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: usuarios_id_usuario_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.usuarios_id_usuario_seq OWNED BY public.usuarios.id_usuario;


--
-- Name: areas id_area; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.areas ALTER COLUMN id_area SET DEFAULT nextval('public.areas_id_area_seq'::regclass);


--
-- Name: categorias id_categoria; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias ALTER COLUMN id_categoria SET DEFAULT nextval('public.categorias_id_categoria_seq'::regclass);


--
-- Name: equipo_almacenamiento id_almacenamiento; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipo_almacenamiento ALTER COLUMN id_almacenamiento SET DEFAULT nextval('public.equipo_almacenamiento_id_almacenamiento_seq'::regclass);


--
-- Name: equipo_gpu id_gpu; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipo_gpu ALTER COLUMN id_gpu SET DEFAULT nextval('public.equipo_gpu_id_gpu_seq'::regclass);


--
-- Name: equipo_mantenimiento_componentes id_componente; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipo_mantenimiento_componentes ALTER COLUMN id_componente SET DEFAULT nextval('public.equipo_mantenimiento_componentes_id_componente_seq'::regclass);


--
-- Name: equipo_mantenimiento_evidencias id_evidencia; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipo_mantenimiento_evidencias ALTER COLUMN id_evidencia SET DEFAULT nextval('public.equipo_mantenimiento_evidencias_id_evidencia_seq'::regclass);


--
-- Name: equipo_mantenimientos id_mantenimiento; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipo_mantenimientos ALTER COLUMN id_mantenimiento SET DEFAULT nextval('public.equipo_mantenimientos_id_mantenimiento_seq'::regclass);


--
-- Name: equipo_ram id_ram; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipo_ram ALTER COLUMN id_ram SET DEFAULT nextval('public.equipo_ram_id_ram_seq'::regclass);


--
-- Name: equipos id_equipo; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipos ALTER COLUMN id_equipo SET DEFAULT nextval('public.equipos_id_equipo_seq'::regclass);


--
-- Name: partes_catalogo id_parte; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.partes_catalogo ALTER COLUMN id_parte SET DEFAULT nextval('public.partes_catalogo_id_parte_seq'::regclass);


--
-- Name: prioridades id_prioridad; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.prioridades ALTER COLUMN id_prioridad SET DEFAULT nextval('public.prioridades_id_prioridad_seq'::regclass);


--
-- Name: ticket_evidencias id_evidencia; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_evidencias ALTER COLUMN id_evidencia SET DEFAULT nextval('public.ticket_evidencias_id_evidencia_seq'::regclass);


--
-- Name: ticket_seguimiento id_seguimiento; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_seguimiento ALTER COLUMN id_seguimiento SET DEFAULT nextval('public.ticket_seguimiento_id_seguimiento_seq'::regclass);


--
-- Name: tickets id_ticket; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets ALTER COLUMN id_ticket SET DEFAULT nextval('public.tickets_id_ticket_seq'::regclass);


--
-- Name: ubicaciones id_ubicacion; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ubicaciones ALTER COLUMN id_ubicacion SET DEFAULT nextval('public.ubicaciones_id_ubicacion_seq'::regclass);


--
-- Name: usuarios id_usuario; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios ALTER COLUMN id_usuario SET DEFAULT nextval('public.usuarios_id_usuario_seq'::regclass);


--
-- Name: areas areas_nombre_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.areas
    ADD CONSTRAINT areas_nombre_key UNIQUE (nombre);


--
-- Name: areas areas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.areas
    ADD CONSTRAINT areas_pkey PRIMARY KEY (id_area);


--
-- Name: categorias categorias_nombre_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias
    ADD CONSTRAINT categorias_nombre_key UNIQUE (nombre);


--
-- Name: categorias categorias_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias
    ADD CONSTRAINT categorias_pkey PRIMARY KEY (id_categoria);


--
-- Name: equipo_almacenamiento equipo_almacenamiento_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipo_almacenamiento
    ADD CONSTRAINT equipo_almacenamiento_pkey PRIMARY KEY (id_almacenamiento);


--
-- Name: equipo_gpu equipo_gpu_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipo_gpu
    ADD CONSTRAINT equipo_gpu_pkey PRIMARY KEY (id_gpu);


--
-- Name: equipo_mantenimiento_componentes equipo_mantenimiento_componentes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipo_mantenimiento_componentes
    ADD CONSTRAINT equipo_mantenimiento_componentes_pkey PRIMARY KEY (id_componente);


--
-- Name: equipo_mantenimiento_evidencias equipo_mantenimiento_evidencias_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipo_mantenimiento_evidencias
    ADD CONSTRAINT equipo_mantenimiento_evidencias_pkey PRIMARY KEY (id_evidencia);


--
-- Name: equipo_mantenimiento_ficha equipo_mantenimiento_ficha_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipo_mantenimiento_ficha
    ADD CONSTRAINT equipo_mantenimiento_ficha_pkey PRIMARY KEY (id_mantenimiento);


--
-- Name: equipo_mantenimientos equipo_mantenimientos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipo_mantenimientos
    ADD CONSTRAINT equipo_mantenimientos_pkey PRIMARY KEY (id_mantenimiento);


--
-- Name: equipo_ram equipo_ram_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipo_ram
    ADD CONSTRAINT equipo_ram_pkey PRIMARY KEY (id_ram);


--
-- Name: equipos equipos_numero_serie_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipos
    ADD CONSTRAINT equipos_numero_serie_key UNIQUE (numero_serie);


--
-- Name: equipos equipos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipos
    ADD CONSTRAINT equipos_pkey PRIMARY KEY (id_equipo);


--
-- Name: partes_catalogo partes_catalogo_nombre_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.partes_catalogo
    ADD CONSTRAINT partes_catalogo_nombre_key UNIQUE (nombre);


--
-- Name: partes_catalogo partes_catalogo_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.partes_catalogo
    ADD CONSTRAINT partes_catalogo_pkey PRIMARY KEY (id_parte);


--
-- Name: prioridades prioridades_nombre_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.prioridades
    ADD CONSTRAINT prioridades_nombre_key UNIQUE (nombre);


--
-- Name: prioridades prioridades_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.prioridades
    ADD CONSTRAINT prioridades_pkey PRIMARY KEY (id_prioridad);


--
-- Name: ticket_evidencias ticket_evidencias_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_evidencias
    ADD CONSTRAINT ticket_evidencias_pkey PRIMARY KEY (id_evidencia);


--
-- Name: ticket_seguimiento ticket_seguimiento_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_seguimiento
    ADD CONSTRAINT ticket_seguimiento_pkey PRIMARY KEY (id_seguimiento);


--
-- Name: tickets tickets_codigo_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_codigo_key UNIQUE (codigo);


--
-- Name: tickets tickets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_pkey PRIMARY KEY (id_ticket);


--
-- Name: ubicaciones ubicaciones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ubicaciones
    ADD CONSTRAINT ubicaciones_pkey PRIMARY KEY (id_ubicacion);


--
-- Name: usuarios usuarios_correo_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_correo_key UNIQUE (correo);


--
-- Name: usuarios usuarios_dni_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_dni_key UNIQUE (dni);


--
-- Name: usuarios usuarios_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_pkey PRIMARY KEY (id_usuario);


--
-- Name: usuarios usuarios_usuario_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_usuario_key UNIQUE (usuario);


--
-- Name: idx_almacenamiento_equipo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_almacenamiento_equipo ON public.equipo_almacenamiento USING btree (id_equipo);


--
-- Name: idx_equipos_activo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_equipos_activo ON public.equipos USING btree (activo);


--
-- Name: idx_equipos_area; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_equipos_area ON public.equipos USING btree (id_area);


--
-- Name: idx_evidencias_mantenimiento; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_evidencias_mantenimiento ON public.equipo_mantenimiento_evidencias USING btree (id_mantenimiento);


--
-- Name: idx_gpu_equipo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_gpu_equipo ON public.equipo_gpu USING btree (id_equipo);


--
-- Name: idx_mant_comp_mant; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_mant_comp_mant ON public.equipo_mantenimiento_componentes USING btree (id_mantenimiento);


--
-- Name: idx_mant_equipo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_mant_equipo ON public.equipo_mantenimientos USING btree (id_equipo);


--
-- Name: idx_mant_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_mant_fecha ON public.equipo_mantenimientos USING btree (fecha_mantenimiento);


--
-- Name: idx_ram_equipo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ram_equipo ON public.equipo_ram USING btree (id_equipo);


--
-- Name: idx_seguimiento_ticket; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_seguimiento_ticket ON public.ticket_seguimiento USING btree (id_ticket);


--
-- Name: idx_tickets_estado; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_tickets_estado ON public.tickets USING btree (estado);


--
-- Name: idx_tickets_solicitante; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_tickets_solicitante ON public.tickets USING btree (id_solicitante);


--
-- Name: idx_tickets_tecnico; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_tickets_tecnico ON public.tickets USING btree (id_tecnico);


--
-- Name: tickets trg_codigo_ticket; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_codigo_ticket BEFORE INSERT ON public.tickets FOR EACH ROW EXECUTE FUNCTION public.generar_codigo_ticket();


--
-- Name: areas areas_id_jefe_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.areas
    ADD CONSTRAINT areas_id_jefe_fkey FOREIGN KEY (id_jefe) REFERENCES public.usuarios(id_usuario);


--
-- Name: equipo_almacenamiento equipo_almacenamiento_id_equipo_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipo_almacenamiento
    ADD CONSTRAINT equipo_almacenamiento_id_equipo_fkey FOREIGN KEY (id_equipo) REFERENCES public.equipos(id_equipo) ON DELETE CASCADE;


--
-- Name: equipo_gpu equipo_gpu_id_equipo_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipo_gpu
    ADD CONSTRAINT equipo_gpu_id_equipo_fkey FOREIGN KEY (id_equipo) REFERENCES public.equipos(id_equipo) ON DELETE CASCADE;


--
-- Name: equipo_mantenimiento_componentes equipo_mantenimiento_componentes_id_mantenimiento_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipo_mantenimiento_componentes
    ADD CONSTRAINT equipo_mantenimiento_componentes_id_mantenimiento_fkey FOREIGN KEY (id_mantenimiento) REFERENCES public.equipo_mantenimientos(id_mantenimiento) ON DELETE CASCADE;


--
-- Name: equipo_mantenimiento_evidencias equipo_mantenimiento_evidencias_id_mantenimiento_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipo_mantenimiento_evidencias
    ADD CONSTRAINT equipo_mantenimiento_evidencias_id_mantenimiento_fkey FOREIGN KEY (id_mantenimiento) REFERENCES public.equipo_mantenimientos(id_mantenimiento) ON DELETE CASCADE;


--
-- Name: equipo_mantenimiento_evidencias equipo_mantenimiento_evidencias_id_usuario_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipo_mantenimiento_evidencias
    ADD CONSTRAINT equipo_mantenimiento_evidencias_id_usuario_fkey FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- Name: equipo_mantenimiento_ficha equipo_mantenimiento_ficha_id_mantenimiento_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipo_mantenimiento_ficha
    ADD CONSTRAINT equipo_mantenimiento_ficha_id_mantenimiento_fkey FOREIGN KEY (id_mantenimiento) REFERENCES public.equipo_mantenimientos(id_mantenimiento) ON DELETE CASCADE;


--
-- Name: equipo_mantenimientos equipo_mantenimientos_id_equipo_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipo_mantenimientos
    ADD CONSTRAINT equipo_mantenimientos_id_equipo_fkey FOREIGN KEY (id_equipo) REFERENCES public.equipos(id_equipo) ON DELETE CASCADE;


--
-- Name: equipo_mantenimientos equipo_mantenimientos_id_tecnico_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipo_mantenimientos
    ADD CONSTRAINT equipo_mantenimientos_id_tecnico_fkey FOREIGN KEY (id_tecnico) REFERENCES public.usuarios(id_usuario);


--
-- Name: equipo_ram equipo_ram_id_equipo_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipo_ram
    ADD CONSTRAINT equipo_ram_id_equipo_fkey FOREIGN KEY (id_equipo) REFERENCES public.equipos(id_equipo) ON DELETE CASCADE;


--
-- Name: equipos equipos_id_area_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipos
    ADD CONSTRAINT equipos_id_area_fkey FOREIGN KEY (id_area) REFERENCES public.areas(id_area);


--
-- Name: equipos equipos_id_responsable_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipos
    ADD CONSTRAINT equipos_id_responsable_fkey FOREIGN KEY (id_responsable) REFERENCES public.usuarios(id_usuario);


--
-- Name: partes_catalogo partes_catalogo_creado_por_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.partes_catalogo
    ADD CONSTRAINT partes_catalogo_creado_por_fkey FOREIGN KEY (creado_por) REFERENCES public.usuarios(id_usuario);


--
-- Name: ticket_evidencias ticket_evidencias_id_ticket_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_evidencias
    ADD CONSTRAINT ticket_evidencias_id_ticket_fkey FOREIGN KEY (id_ticket) REFERENCES public.tickets(id_ticket) ON DELETE CASCADE;


--
-- Name: ticket_evidencias ticket_evidencias_id_usuario_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_evidencias
    ADD CONSTRAINT ticket_evidencias_id_usuario_fkey FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- Name: ticket_seguimiento ticket_seguimiento_id_ticket_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_seguimiento
    ADD CONSTRAINT ticket_seguimiento_id_ticket_fkey FOREIGN KEY (id_ticket) REFERENCES public.tickets(id_ticket) ON DELETE CASCADE;


--
-- Name: ticket_seguimiento ticket_seguimiento_id_usuario_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_seguimiento
    ADD CONSTRAINT ticket_seguimiento_id_usuario_fkey FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- Name: tickets tickets_id_area_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_id_area_fkey FOREIGN KEY (id_area) REFERENCES public.areas(id_area);


--
-- Name: tickets tickets_id_categoria_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_id_categoria_fkey FOREIGN KEY (id_categoria) REFERENCES public.categorias(id_categoria);


--
-- Name: tickets tickets_id_prioridad_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_id_prioridad_fkey FOREIGN KEY (id_prioridad) REFERENCES public.prioridades(id_prioridad);


--
-- Name: tickets tickets_id_solicitante_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_id_solicitante_fkey FOREIGN KEY (id_solicitante) REFERENCES public.usuarios(id_usuario);


--
-- Name: tickets tickets_id_tecnico_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_id_tecnico_fkey FOREIGN KEY (id_tecnico) REFERENCES public.usuarios(id_usuario);


--
-- Name: tickets tickets_id_ubicacion_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_id_ubicacion_fkey FOREIGN KEY (id_ubicacion) REFERENCES public.ubicaciones(id_ubicacion);


--
-- Name: usuarios usuarios_id_area_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_id_area_fkey FOREIGN KEY (id_area) REFERENCES public.areas(id_area);


--
-- PostgreSQL database dump complete
--


