<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema para la Gestión de Asistencias</title>
    <link rel="stylesheet" href="./Styles.css">
</head>
<body>

    <header>
        <nav>
            <a href="#inicio">
            <img src="./Imagenes/image.png" alt="Logo Sistema" id="Logo_Arriba">
            </a>
            <a href="#sistema">Sobre el Sistema</a>
            <a href="#nosotros">Sobre Nosotros</a>
            <a href="#comentarios">Comentarios</a>
            <a href="#demo">Demo del Sistema</a>
        </nav>
    </header>

    <main>
        <article id="inicio">
            <h1>SISTEMA PARA LA GESTIÓN DE ASISTENCIAS</h1>
            <p>Arquitectura de vanguardia para la gestión de asistencias.</p>
        </article>

        <section id="sistema">
            <h2>Ingeniería Operativa</h2>
            <p>Una solución robusta diseñada para sustituir flujos de trabajo obsoletos por una infraestructura escalable.</p>
            
            <article>
                <h3>Núcleo de Gestión</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Módulo Funcional</th>
                            <th>Capacidades Administrativas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Empresas y Sedes</strong></td>
                            <td>Centralización total de la estructura organizacional corporativa.</td>
                        </tr>
                        <tr>
                            <td><strong>Departamentos</strong></td>
                            <td>Segmentación por áreas para una auditoría más precisa de los recursos.</td>
                        </tr>
                        <tr>
                            <td><strong>Fichas de Empleado</strong></td>
                            <td>CRUD completo con historial de movimientos y asignación de roles.</td>
                        </tr>
                    </tbody>
                </table>
            </article>

            <article>
                <h3>Flujos Automatizados</h3>
                <ul>
                    <li><strong>Procesamiento .txt:</strong> Algoritmo de lectura masiva para la carga de asistencias externas.</li>
                    <li><strong>Planificación:</strong> Calendarios inteligentes adaptables a turnos rotativos o fijos.</li>
                    <li><strong>Incidencias:</strong> Sistema de ticketing interno para justificar retardos o faltas.</li>
                    <li><strong>Reportes TXT:</strong> Salida de datos depurada lista para procesos de auditoría legal.</li>
                </ul>
            </article>

            <article>
                <h3>Seguridad Perimetral</h3>
                <p>El módulo de <strong>Auditorías</strong> ofrece un registro inmutable de cada interacción realizada por los usuarios, asegurando que la integridad de los datos nunca se vea comprometida.</p>
            </article>
        </section>

        <section id="nosotros">
            <h2>El Colectivo</h2>
            <p>Somos desarrolladores de informática con una visión: transformar la complejidad en simplicidad técnica. Nuestro trabajo se fundamenta en la creación de herramientas que no solo funcionen, sino que optimicen la vida operativa de las empresas.</p>
            <p>Nuestra metodología ignora lo convencional para enfocarse en la eficiencia pura del código y la seguridad de la información. Cada línea escrita tiene un propósito, cada base de datos una razón de ser.</p>
            
            <ul id="equipo-lista">
                <li class="desarrollador">
                    <figure>
                        <img src="./Imagenes/fotoLuis.jpeg" alt="Luis">
                        <figcaption><strong>Especialista en Base de Datos | Luis Colmenarez</strong></figcaption>
                    </figure>
                </li>
                <li class="desarrollador">
                    <figure>
                        <img src="https://images.unsplash.com/photo-1580489944761-15a19d654956?q=80&w=300&h=450&fit=crop" alt="Santiagogogogo">
                        <figcaption><strong>Analista Integral de Datos | Santiago Arrieche</strong></figcaption>
                    </figure>
                </li>
                <li class="desarrollador">
                    <figure>
                        <img src="https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?q=80&w=300&h=450&fit=crop" alt="EmilyGuapota">
                        <figcaption><strong>Diseñadora y Desarrolladora Frontend | Emily Heredia</strong></figcaption>
                    </figure>
                </li>
                <li class="desarrollador">
                    <figure>
                        <img src="https://images.unsplash.com/photo-1560250097-0b93528c311a?q=80&w=300&h=450&fit=crop" alt="Vicleydy">
                        <figcaption><strong>Programador | Vicleydy Granda</strong></figcaption>
                    </figure>
                </li>
            </ul>
        </section>

        <section id="comentarios">
            <h2>Evolución Colaborativa</h2>
            <p>Tu opinión es el motor de nuestras actualizaciones. Propón mejoras para el sistema.</p>
            <form id="form-comentarios">
                <input type="text" name="nombre" placeholder="Nombre completo" required>
                <input type="email" name="correo" placeholder="Email corporativo" required>
                <textarea name="mensaje" rows="5" placeholder="Describe tu opinión..." required></textarea>
                <button type="submit">Enviar Propuesta</button>
            </form>
        </section>

        <section id="demo">
            <h2>Acceso a la Versión Demo</h2>
            <p>Solicita el paquete de instalación para Android y prueba el ecosistema completo.</p>
            <button type="button" onclick="descargarDemo()">Obtener APK de Prueba</button>
        </section>
    </main>

    <footer>
        <img src="./Imagenes/image.png" alt="Logo Footer" id="Logo_Abajo">
        <section id="contacto">
            <p><strong>NODO DE SOPORTE</strong></p>
            <p>tecnologia.iujo@soporte.com</p>
            <p>Barquisimeto, Venezuela.</p>
        </section>
        <hr>
        <p>&copy; <?php echo date("Y"); ?> - Desarrollado por Especialistas en Informática. IUJO.</p>
    </footer>

    <script src="./CosasChidas.js"></script>
</body>
</html>