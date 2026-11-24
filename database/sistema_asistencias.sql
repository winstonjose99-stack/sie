-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: sistema_asistencias
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `asistencias`
--

DROP TABLE IF EXISTS `asistencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asistencias` (
  `id_asistencia` int(11) NOT NULL AUTO_INCREMENT,
  `id_estudiante` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `estado` varchar(20) NOT NULL,
  PRIMARY KEY (`id_asistencia`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asistencias`
--

LOCK TABLES `asistencias` WRITE;
/*!40000 ALTER TABLE `asistencias` DISABLE KEYS */;
INSERT INTO `asistencias` VALUES (1,2,'2026-11-12','Ausente'),(2,1,'2026-10-07','Ausente'),(3,1,'2026-10-07','Ausente'),(4,3,'2026-10-07','Ausente'),(5,1,'2026-10-07','Ausente'),(6,1,'2026-10-08','Ausente'),(7,1,'2026-10-08','Ausente'),(8,1,'2026-10-08','Ausente'),(9,1,'2026-10-08','Ausente');
/*!40000 ALTER TABLE `asistencias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `docentes`
--

DROP TABLE IF EXISTS `docentes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `docentes` (
  `id_docente` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `cedula` varchar(20) NOT NULL,
  `correo` varchar(50) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `id_institucion` int(11) NOT NULL,
  PRIMARY KEY (`id_docente`),
  KEY `fk_docentes_institucion` (`id_institucion`),
  CONSTRAINT `fk_docentes_institucion` FOREIGN KEY (`id_institucion`) REFERENCES `instituciones` (`id_institucion`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `docentes`
--

LOCK TABLES `docentes` WRITE;
/*!40000 ALTER TABLE `docentes` DISABLE KEYS */;
INSERT INTO `docentes` VALUES (1,'maria','herrera','23413444','qwaseqwe@gmail.com','2147483647',2),(2,'winston','herrera','27374153','qweqweqwe@gmail.com','2147483647',2),(3,'winston','asdsadasd','27374153','asdaerwqe@gmail.com','2147483647',2),(4,'Jose','Rodriguez','27374153','winston@gmail.com','2147483647',2),(5,'Jose Villa','','27374153','winston100@gmail.com','883723',2),(6,'Javier','Linares','27374153','javier@gmail.com','2147483647',2),(7,'Victor','','27374153','winstonjose99@gmail.com','2147483647',2),(8,'Marcelo','Contreras','27374153','marcelo@gmail.com','2147483647',2);
/*!40000 ALTER TABLE `docentes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estudiantes`
--

DROP TABLE IF EXISTS `estudiantes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `estudiantes` (
  `id_estudiante` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `cedula` varchar(20) NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `sexo` varchar(50) DEFAULT NULL,
  `enfermedad_condicion` text DEFAULT NULL,
  `id_seccion` int(11) DEFAULT NULL,
  `id_representante` int(11) DEFAULT NULL,
  `id_periodo` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_estudiante`),
  KEY `fk_estudiantes_seccion` (`id_seccion`),
  KEY `fk_estudiantes_representante` (`id_representante`),
  KEY `fk_estudiantes_periodo` (`id_periodo`),
  CONSTRAINT `fk_estudiantes_periodo` FOREIGN KEY (`id_periodo`) REFERENCES `periodos_academicos` (`id_periodo`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_estudiantes_representante` FOREIGN KEY (`id_representante`) REFERENCES `representantes` (`id_representante`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_estudiantes_seccion` FOREIGN KEY (`id_seccion`) REFERENCES `secciones` (`id_seccion`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estudiantes`
--

LOCK TABLES `estudiantes` WRITE;
/*!40000 ALTER TABLE `estudiantes` DISABLE KEYS */;
INSERT INTO `estudiantes` VALUES (7,'Giuseppe','Salas','31450112','2011-04-15','Masculino','Ninguna',8,9,4),(8,'Giuseppe','Pinto','31450118','2011-08-22','Masculino','Ninguna',8,9,4),(15,'Carlos','Hernandez','3094942123','2016-01-22','Masculino','asma',9,10,4),(16,'Luis ','Torres','27374153','2009-06-17','Masculino','Asma',9,11,4),(17,'Marcos','Torres','27374153','2016-01-07','Masculino','Asma',11,12,4),(18,'Valeria','Mendoza','31890234','2011-02-10','Femenino','Ninguna',8,3,4),(19,'Santiago','Rivas','31904512','2011-06-18','Masculino','Asma leve',8,8,4),(20,'Camila','Colmenares','32015489','2011-11-03','Femenino','Ninguna',8,9,4),(21,'Diego','Urdaneta','31782341','2011-01-25','Masculino','Ninguna',8,10,4),(22,'Mariana','Zambrano','32110984','2011-09-14','Femenino','Alergia estacional',8,11,4),(23,'Alejandro','Perdomo','31654902','2011-03-29','Masculino','Ninguna',8,12,4),(24,'Gabriela','Quintero','32098711','2011-07-07','Femenino','Ninguna',8,3,4),(25,'Sebastián','Escalona','31845670','2011-12-19','Masculino','Uso de lentes correctivos',8,8,4),(26,'Daniela','Montilla','32145009','2011-05-11','Femenino','Ninguna',8,11,4),(27,'Andrés','Villamizar','31998230','2011-10-30','Masculino','Ninguna',8,12,4);
/*!40000 ALTER TABLE `estudiantes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inasistencias`
--

DROP TABLE IF EXISTS `inasistencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inasistencias` (
  `id_inasistencia` int(11) NOT NULL AUTO_INCREMENT,
  `id_estudiante` int(11) NOT NULL,
  `id_materia` int(11) NOT NULL,
  `id_docente` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `estado` enum('Presente','Ausente','Justificado') NOT NULL DEFAULT 'Presente',
  `motivo` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id_inasistencia`),
  KEY `id_estudiante` (`id_estudiante`),
  KEY `id_materia` (`id_materia`),
  KEY `id_docente` (`id_docente`),
  CONSTRAINT `inasistencias_ibfk_1` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON DELETE CASCADE,
  CONSTRAINT `inasistencias_ibfk_2` FOREIGN KEY (`id_materia`) REFERENCES `materias` (`id_materia`) ON DELETE CASCADE,
  CONSTRAINT `inasistencias_ibfk_3` FOREIGN KEY (`id_docente`) REFERENCES `docentes` (`id_docente`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inasistencias`
--

LOCK TABLES `inasistencias` WRITE;
/*!40000 ALTER TABLE `inasistencias` DISABLE KEYS */;
INSERT INTO `inasistencias` VALUES (11,16,13,7,'2026-11-05','Justificado','Justificado (registro anterior)'),(12,16,13,7,'2026-11-05','Justificado','Justificado (registro anterior)'),(13,16,13,7,'2026-11-05','Justificado','Justificado (registro anterior)'),(14,17,15,8,'2026-11-05','Justificado','Justificado (registro anterior)'),(17,7,13,4,'2026-11-18','Presente',NULL),(18,8,13,4,'2026-11-18','Presente',NULL),(19,18,13,4,'2026-11-18','Presente',NULL),(20,19,13,4,'2026-11-18','Ausente',NULL),(21,20,13,4,'2026-11-18','Presente',NULL),(22,21,13,4,'2026-11-18','Presente',NULL),(23,22,13,4,'2026-11-18','Presente',NULL),(24,23,13,4,'2026-11-18','Ausente',NULL),(25,24,13,4,'2026-11-18','Presente',NULL),(26,25,13,4,'2026-11-18','Justificado','Cita Medica'),(27,26,13,4,'2026-11-18','Presente',NULL),(28,27,13,4,'2026-11-18','Presente',NULL),(32,20,13,4,'2026-11-17','Presente','Asistió a clase'),(33,25,13,4,'2026-11-17','Presente','Asistió a clase'),(34,18,13,4,'2026-11-17','Presente','Asistió a clase'),(35,26,13,4,'2026-11-17','Presente','Asistió a clase'),(36,23,13,4,'2026-11-17','Presente','Asistió a clase'),(37,8,13,4,'2026-11-17','Presente','Asistió a clase'),(38,24,13,4,'2026-11-17','Presente','Asistió a clase'),(39,19,13,4,'2026-11-17','Ausente',NULL),(40,7,13,4,'2026-11-17','Presente','Asistió a clase'),(41,21,13,4,'2026-11-17','Presente','Asistió a clase'),(42,27,13,4,'2026-11-17','Presente','Asistió a clase'),(43,22,13,4,'2026-11-17','Ausente',NULL);
/*!40000 ALTER TABLE `inasistencias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `instituciones`
--

DROP TABLE IF EXISTS `instituciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `instituciones` (
  `id_institucion` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `direccion` varchar(200) NOT NULL,
  PRIMARY KEY (`id_institucion`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `instituciones`
--

LOCK TABLES `instituciones` WRITE;
/*!40000 ALTER TABLE `instituciones` DISABLE KEYS */;
INSERT INTO `instituciones` VALUES (2,'U E REPUBLICA DEL ECUADOR','av san martin'),(3,'U E JARDIN FRANCISCANO','CHAPELLIN'),(4,'El Archerpol','av sas martin');
/*!40000 ALTER TABLE `instituciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `materias`
--

DROP TABLE IF EXISTS `materias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `materias` (
  `id_materia` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_materia` varchar(50) NOT NULL,
  `id_docente` int(11) NOT NULL,
  PRIMARY KEY (`id_materia`),
  KEY `fk_materias_docente` (`id_docente`),
  CONSTRAINT `fk_materias_docente` FOREIGN KEY (`id_docente`) REFERENCES `docentes` (`id_docente`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `materias`
--

LOCK TABLES `materias` WRITE;
/*!40000 ALTER TABLE `materias` DISABLE KEYS */;
INSERT INTO `materias` VALUES (9,'Matematicas',3),(10,'Ingles',1),(11,'Quimica',5),(12,'Fisica',6),(13,'Informatica',4),(14,'Quimica',7),(15,'Programacion',8);
/*!40000 ALTER TABLE `materias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notas_academicas`
--

DROP TABLE IF EXISTS `notas_academicas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notas_academicas` (
  `id_nota` int(11) NOT NULL AUTO_INCREMENT,
  `id_estudiante` int(11) NOT NULL,
  `id_materia` int(11) NOT NULL,
  `id_docente` int(11) DEFAULT NULL,
  `id_periodo` int(11) DEFAULT NULL,
  `lapso` varchar(30) NOT NULL DEFAULT '1er Lapso',
  `evaluacion` varchar(120) NOT NULL,
  `calificacion` decimal(5,2) NOT NULL,
  `observacion` varchar(200) DEFAULT NULL,
  `fecha_registro` date NOT NULL,
  PRIMARY KEY (`id_nota`),
  KEY `idx_notas_est` (`id_estudiante`),
  KEY `idx_notas_mat` (`id_materia`),
  KEY `fk_notas_docente` (`id_docente`),
  KEY `fk_notas_periodo` (`id_periodo`),
  CONSTRAINT `fk_notas_docente` FOREIGN KEY (`id_docente`) REFERENCES `docentes` (`id_docente`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_notas_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notas_materia` FOREIGN KEY (`id_materia`) REFERENCES `materias` (`id_materia`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notas_periodo` FOREIGN KEY (`id_periodo`) REFERENCES `periodos_academicos` (`id_periodo`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notas_academicas`
--

LOCK TABLES `notas_academicas` WRITE;
/*!40000 ALTER TABLE `notas_academicas` DISABLE KEYS */;
INSERT INTO `notas_academicas` VALUES (1,7,13,4,4,'1er Lapso','Evaluación Práctica de Ofimática',18.50,'Excelente dominio en hojas de cálculo','2026-11-10'),(2,7,13,4,4,'1er Lapso','Taller de Lógica y Algoritmos',16.00,'Buen desempeño y participación','2026-11-18'),(3,8,13,4,4,'1er Lapso','Evaluación Práctica de Ofimática',17.00,'Cumplió con todos los objetivos','2026-11-10'),(4,8,13,4,4,'1er Lapso','Taller de Lógica y Algoritmos',19.00,'Sobresaliente resolución de problemas','2026-11-18'),(5,20,13,4,4,'1er Lapso','Evaluación Práctica de Ofimática',19.50,'Trabajo impecable y puntual','2026-11-10'),(6,18,13,4,4,'1er Lapso','Evaluación Práctica de Ofimática',18.00,'Muy buena presentación','2026-11-10'),(7,24,13,4,4,'1er Lapso','Evaluación Práctica de Ofimática',16.50,'Buen progreso en clase','2026-11-10'),(8,19,13,4,4,'1er Lapso','Evaluación Práctica de Ofimática',14.00,'Debe reforzar asistencia para próximas prácticas','2026-11-10'),(9,25,13,4,4,'1er Lapso','Evaluación Práctica de Ofimática',17.50,'Participación constante','2026-11-10'),(10,21,13,4,4,'1er Lapso','Evaluación Práctica de Ofimática',15.50,'Aprobado, mejorar orden de entrega','2026-11-10'),(11,22,13,4,4,'1er Lapso','Evaluación Práctica de Ofimática',18.00,'Excelente actitud en el laboratorio','2026-11-10'),(12,26,13,4,4,'1er Lapso','Evaluación Práctica de Ofimática',19.00,'Destacada en programación básica','2026-11-10'),(13,23,13,4,4,'1er Lapso','Evaluación Práctica de Ofimática',13.50,'Requiere repasar temas de la clase perdida','2026-11-10'),(14,27,13,4,4,'1er Lapso','Evaluación Práctica de Ofimática',17.00,'Buen rendimiento general','2026-11-10');
/*!40000 ALTER TABLE `notas_academicas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notificaciones_representante`
--

DROP TABLE IF EXISTS `notificaciones_representante`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notificaciones_representante` (
  `id_notificacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_representante` int(11) NOT NULL,
  `id_estudiante` int(11) DEFAULT NULL,
  `tipo` varchar(30) NOT NULL DEFAULT 'asistencia',
  `titulo` varchar(150) NOT NULL,
  `mensaje` text NOT NULL,
  `leida` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_notificacion`),
  KEY `idx_notif_rep` (`id_representante`),
  KEY `idx_notif_est` (`id_estudiante`),
  CONSTRAINT `fk_notif_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_notif_representante` FOREIGN KEY (`id_representante`) REFERENCES `representantes` (`id_representante`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notificaciones_representante`
--

LOCK TABLES `notificaciones_representante` WRITE;
/*!40000 ALTER TABLE `notificaciones_representante` DISABLE KEYS */;
INSERT INTO `notificaciones_representante` VALUES (1,9,7,'asistencia','Asistencia confirmada — Informatica','Giuseppe Salas asistió puntualmente a la clase de Informatica (Sección 1A) el 2026-11-18.',0,'2026-11-18 08:30:00'),(2,9,7,'nota','Nueva nota académica — Informatica','Se registró 16.00 / 20 para Giuseppe Salas en Taller de Lógica y Algoritmos (1er Lapso).',0,'2026-11-18 10:15:00'),(3,9,8,'nota','Nueva nota académica — Informatica','Se registró 19.00 / 20 para Giuseppe Pinto en Taller de Lógica y Algoritmos (1er Lapso).',0,'2026-11-18 10:16:00'),(4,8,19,'ausencia','Inasistencia registrada — Informatica','Santiago Rivas no asistió a la clase de Informatica (Sección 1A) el día 2026-11-18. Estado: Ausente.',0,'2026-11-18 08:35:00'),(5,8,25,'justificado','Inasistencia justificada — Informatica','Sebastián Escalona presentó inasistencia justificada en Informatica (Sección 1A) el 2026-11-18. Motivo: Cita Medica.',0,'2026-11-18 08:36:00'),(6,12,23,'ausencia','Inasistencia registrada — Informatica','Alejandro Perdomo no asistió a la clase de Informatica (Sección 1A) el día 2026-11-18. Estado: Ausente.',0,'2026-11-18 08:37:00'),(7,11,22,'ausencia','Inasistencia registrada — Informatica','Mariana Zambrano no asistió a la clase de Informatica (Sección 1A) el día 2026-11-17. Estado: Ausente.',0,'2026-11-17 08:40:00'),(8,3,18,'asistencia','Asistencia confirmada — Informatica','Valeria Mendoza asistió a la clase de Informatica (Sección 1A) el 2026-11-18.',0,'2026-11-18 08:30:00'),(9,3,18,'nota','Nueva nota académica — Informatica','Se registró 18.00 / 20 para Valeria Mendoza en Evaluación Práctica de Ofimática (1er Lapso).',0,'2026-11-10 11:00:00'),(10,10,21,'nota','Nueva nota académica — Informatica','Se registró 15.50 / 20 para Diego Urdaneta en Evaluación Práctica de Ofimática (1er Lapso).',0,'2026-11-10 11:05:00');
/*!40000 ALTER TABLE `notificaciones_representante` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `periodos_academicos`
--

DROP TABLE IF EXISTS `periodos_academicos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `periodos_academicos` (
  `id_periodo` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_periodo` varchar(50) NOT NULL,
  `activo` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id_periodo`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `periodos_academicos`
--

LOCK TABLES `periodos_academicos` WRITE;
/*!40000 ALTER TABLE `periodos_academicos` DISABLE KEYS */;
INSERT INTO `periodos_academicos` VALUES (1,'2025-2026',0),(2,'2025-2026',0),(3,'2026-2027',0),(4,'2025-2026',1);
/*!40000 ALTER TABLE `periodos_academicos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `registro`
--

DROP TABLE IF EXISTS `registro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `registro` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(100) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `correo` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `registro`
--

LOCK TABLES `registro` WRITE;
/*!40000 ALTER TABLE `registro` DISABLE KEYS */;
INSERT INTO `registro` VALUES (4,'winston100','$2y$10$It3IXr3iQB.SlOygxeTYP.ITE9b2O7eLSuiaWc5WCjMYQLHtJJ.Xq','winstonjose99@gmail.com'),(5,'winston1001','$2y$10$w/96h6/WGd/YXn.p3ri7d.QveJ1uQVlHrJCj1NRVTrD.z3fPwuTRu','winstonjose100@gmail.com');
/*!40000 ALTER TABLE `registro` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `representantes`
--

DROP TABLE IF EXISTS `representantes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `representantes` (
  `id_representante` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `cedula` varchar(20) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  PRIMARY KEY (`id_representante`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `representantes`
--

LOCK TABLES `representantes` WRITE;
/*!40000 ALTER TABLE `representantes` DISABLE KEYS */;
INSERT INTO `representantes` VALUES (3,'miladis','herrera','14965665','584124710360'),(8,'Orlexy','Materano','27374153','584124710360'),(9,'Zaida','Zenith','27374153','584124710360'),(10,'Roberis','Torres','2147483647','584124710360'),(11,'Luis ','Rodriguez','272734153','584124710360'),(12,'Ana','Torres','27374153','584124710360');
/*!40000 ALTER TABLE `representantes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `secciones`
--

DROP TABLE IF EXISTS `secciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `secciones` (
  `id_seccion` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_seccion` varchar(50) NOT NULL,
  `id_institucion` int(11) NOT NULL,
  `id_docente` int(11) NOT NULL,
  `id_materia` int(11) NOT NULL,
  `id_periodo` int(11) DEFAULT 1,
  PRIMARY KEY (`id_seccion`),
  KEY `fk_secciones_periodos` (`id_periodo`),
  KEY `fk_secciones_institucion` (`id_institucion`),
  KEY `fk_secciones_docente` (`id_docente`),
  KEY `fk_secciones_materia` (`id_materia`),
  CONSTRAINT `fk_secciones_docente` FOREIGN KEY (`id_docente`) REFERENCES `docentes` (`id_docente`) ON UPDATE CASCADE,
  CONSTRAINT `fk_secciones_institucion` FOREIGN KEY (`id_institucion`) REFERENCES `instituciones` (`id_institucion`) ON UPDATE CASCADE,
  CONSTRAINT `fk_secciones_materia` FOREIGN KEY (`id_materia`) REFERENCES `materias` (`id_materia`) ON UPDATE CASCADE,
  CONSTRAINT `fk_secciones_periodos` FOREIGN KEY (`id_periodo`) REFERENCES `periodos_academicos` (`id_periodo`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `secciones`
--

LOCK TABLES `secciones` WRITE;
/*!40000 ALTER TABLE `secciones` DISABLE KEYS */;
INSERT INTO `secciones` VALUES (8,'1A',2,4,13,4),(9,'2 A',2,1,12,4),(10,'3 B',2,7,13,4),(11,'2 A',4,8,15,4);
/*!40000 ALTER TABLE `secciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'sistema_asistencias'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-24 11:35:47
