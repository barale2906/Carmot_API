# Lista de Verificación: Sistema de Registro de Asistencia

## 📋 Resumen de Cambios Aplicados

✅ **Nomenclatura**: Todos los modelos/tablas inician con "Asistencia"

-   `AsistenciaClaseProgramada` (tabla: `asistencia_clases_programadas`)
-   `AsistenciaConfiguracion` (tabla: `asistencia_configuraciones`)
-   `Asistencia` (tabla: `asistencias` - ya existe)

✅ **Registro simplificado**: Solo se requiere grupo, no ciclo

-   El sistema busca automáticamente ciclos activos/vigentes
-   Muestra estudiantes de todos los ciclos activos que contienen el grupo

✅ **Archivos existentes a usar**:

-   `app/Models/Academico/Asistencia.php`
-   `database/migrations/2025_11_29_201012_create_asistencias_table.php`
-   `app/Http/Controllers/Api/Academico/AsistenciaController.php`
-   `app/Http/Requests/Api/StoreAsistenciaRequest.php`
-   `app/Http/Requests/Api/UpdateAsistenciaRequest.php`
-   `app/Http/Resources/Api/Academico/AsistenciaResource.php`
-   `database/seeders/AsistenciaSeeder.php`

---

## ✅ Lista de Verificación Consecutiva

1. [x] Abrir migración generada: `database/migrations/2025_11_29_213716_create_asistencia_clase_programadas_table.php`
2. [x] Agregar campo `grupo_id` (foreign key a grupos)
3. [x] Agregar campo `ciclo_id` (foreign key a ciclos)
4. [x] Agregar campo `fecha_clase` (date)
5. [x] Agregar campo `hora_inicio` (time)
6. [x] Agregar campo `hora_fin` (time)
7. [x] Agregar campo `duracion_horas` (decimal 4,2)
8. [x] Agregar campo `estado` (enum: programada, dictada, cancelada, reprogramada)
9. [x] Agregar campo `observaciones` (text, nullable)
10. [x] Agregar campo `creado_por_id` (foreign key a users, nullable)
11. [x] Agregar campo `fecha_programacion` (datetime, nullable)
12. [x] Agregar `soft_deletes` a la migración de asistencia_clases_programadas
13. [x] Crear índice único `unique_clase_grupo_ciclo_fecha_hora` en asistencia_clases_programadas
14. [x] Crear índice `idx_fecha_clase` en asistencia_clases_programadas
15. [x] Crear índice `idx_ciclo_grupo` en asistencia_clases_programadas
16. [x] Crear índice `idx_estado` en asistencia_clases_programadas
17. [x] Abrir migración generada: `database/migrations/2025_11_29_213733_update_asistencias_table.php`
18. [x] Agregar campo `estudiante_id` (foreign key a users) a tabla asistencias
19. [x] Agregar campo `clase_programada_id` (foreign key a asistencia_clases_programadas) a tabla asistencias
20. [x] Agregar campo `grupo_id` (foreign key a grupos) a tabla asistencias
21. [x] Agregar campo `ciclo_id` (foreign key a ciclos) a tabla asistencias
22. [x] Agregar campo `modulo_id` (foreign key a modulos) a tabla asistencias
23. [x] Agregar campo `curso_id` (foreign key a cursos) a tabla asistencias
24. [x] Agregar campo `estado` (enum: presente, ausente, justificado, tardanza) a tabla asistencias
25. [x] Agregar campo `hora_registro` (time, nullable) a tabla asistencias
26. [x] Agregar campo `observaciones` (text, nullable) a tabla asistencias
27. [x] Agregar campo `registrado_por_id` (foreign key a users) a tabla asistencias
28. [x] Agregar campo `fecha_registro` (datetime) a tabla asistencias
29. [x] Agregar `soft_deletes` a la tabla asistencias si no existe
30. [x] Crear índice único `unique_asistencia_estudiante_clase` en tabla asistencias
31. [x] Crear índice `idx_estudiante_ciclo` en tabla asistencias
32. [x] Crear índice `idx_estudiante_grupo` en tabla asistencias
33. [x] Crear índice `idx_estudiante_curso` en tabla asistencias
34. [x] Crear índice `idx_clase_programada` en tabla asistencias
35. [x] Crear índice `idx_estado` en tabla asistencias
36. [x] Crear índice `idx_fecha_registro` en tabla asistencias
37. [x] Abrir migración generada: `database/migrations/2025_11_29_213724_create_asistencia_configuracions_table.php`
38. [x] Agregar campo `curso_id` (foreign key a cursos, nullable) a asistencia_configuraciones
39. [x] Agregar campo `modulo_id` (foreign key a modulos, nullable) a asistencia_configuraciones
40. [x] Agregar campo `porcentaje_minimo` (decimal 5,2, default 80.00) a asistencia_configuraciones
41. [x] Agregar campo `horas_minimas` (integer, nullable) a asistencia_configuraciones
42. [x] Agregar campo `aplicar_justificaciones` (boolean, default true) a asistencia_configuraciones
43. [x] Agregar campo `perder_por_fallas` (boolean, default true) a asistencia_configuraciones
44. [x] Agregar campo `fecha_inicio_vigencia` (date, nullable) a asistencia_configuraciones
45. [x] Agregar campo `fecha_fin_vigencia` (date, nullable) a asistencia_configuraciones
46. [x] Agregar campo `observaciones` (text, nullable) a asistencia_configuraciones
47. [x] Agregar `soft_deletes` a la migración de asistencia_configuraciones
48. [x] Crear índice `idx_curso_modulo` en asistencia_configuraciones
49. [x] Crear índice `idx_vigencia` en asistencia_configuraciones
50. [x] Abrir modelo: `app/Models/Academico/AsistenciaClaseProgramada.php`
51. [x] Configurar namespace `App\Models\Academico` en AsistenciaClaseProgramada
52. [x] Agregar trait `SoftDeletes` a AsistenciaClaseProgramada
53. [x] Agregar trait `HasFilterScopes` a AsistenciaClaseProgramada
54. [x] Agregar trait `HasSortingScopes` a AsistenciaClaseProgramada
55. [x] Agregar trait `HasRelationScopes` a AsistenciaClaseProgramada
56. [x] Definir `$guarded` o `$fillable` en AsistenciaClaseProgramada
57. [x] Definir `$casts` (fechas, decimales, enum) en AsistenciaClaseProgramada
58. [x] Crear relación `grupo()` → BelongsTo Grupo en AsistenciaClaseProgramada
59. [x] Crear relación `ciclo()` → BelongsTo Ciclo en AsistenciaClaseProgramada
60. [x] Crear relación `creadoPor()` → BelongsTo User en AsistenciaClaseProgramada
61. [x] Crear relación `asistencias()` → HasMany Asistencia en AsistenciaClaseProgramada
62. [x] Crear scope `scopeByGrupo($query, $grupoId)` en AsistenciaClaseProgramada
63. [x] Crear scope `scopeByCiclo($query, $cicloId)` en AsistenciaClaseProgramada
64. [x] Crear scope `scopeByFecha($query, $fecha)` en AsistenciaClaseProgramada
65. [x] Crear scope `scopeDictadas($query)` en AsistenciaClaseProgramada
66. [x] Crear scope `scopeProgramadas($query)` en AsistenciaClaseProgramada
67. [x] Crear scope `scopeCiclosActivos($query)` en AsistenciaClaseProgramada
68. [x] Crear método `calcularDuracionHoras(): float` en AsistenciaClaseProgramada
69. [x] Crear método `estaEnRangoFechasGrupo(): bool` en AsistenciaClaseProgramada
70. [x] Crear método `puedeRegistrarAsistencia(): bool` en AsistenciaClaseProgramada
71. [x] Abrir modelo: `app/Models/Academico/Asistencia.php`
72. [x] Agregar trait `SoftDeletes` a modelo Asistencia
73. [x] Agregar trait `HasFilterScopes` a modelo Asistencia
74. [x] Agregar trait `HasSortingScopes` a modelo Asistencia
75. [x] Agregar trait `HasRelationScopes` a modelo Asistencia
76. [x] Definir `$guarded` o `$fillable` en modelo Asistencia
77. [x] Definir `$casts` (fechas, decimales, enum) en modelo Asistencia
78. [x] Crear relación `estudiante()` → BelongsTo User en modelo Asistencia
79. [x] Crear relación `claseProgramada()` → BelongsTo AsistenciaClaseProgramada en modelo Asistencia
80. [x] Crear relación `grupo()` → BelongsTo Grupo en modelo Asistencia
81. [x] Crear relación `ciclo()` → BelongsTo Ciclo en modelo Asistencia
82. [x] Crear relación `modulo()` → BelongsTo Modulo en modelo Asistencia
83. [x] Crear relación `curso()` → BelongsTo Curso en modelo Asistencia
84. [x] Crear relación `registradoPor()` → BelongsTo User en modelo Asistencia
85. [x] Crear scope `scopeByEstudiante($query, $estudianteId)` en modelo Asistencia
86. [x] Crear scope `scopeByGrupo($query, $grupoId)` en modelo Asistencia
87. [x] Crear scope `scopeByCiclo($query, $cicloId)` en modelo Asistencia
88. [x] Crear scope `scopeByCurso($query, $cursoId)` en modelo Asistencia
89. [x] Crear scope `scopePresentes($query)` en modelo Asistencia
90. [x] Crear scope `scopeAusentes($query)` en modelo Asistencia
91. [x] Crear scope `scopeJustificadas($query)` en modelo Asistencia
92. [x] Crear método `esPresente(): bool` en modelo Asistencia
93. [x] Crear método `esJustificada(): bool` en modelo Asistencia
94. [x] Crear método `contarParaMinimo(): bool` en modelo Asistencia
95. [x] Abrir modelo: `app/Models/Academico/AsistenciaConfiguracion.php`
96. [x] Configurar namespace `App\Models\Academico` en AsistenciaConfiguracion
97. [x] Agregar trait `SoftDeletes` a AsistenciaConfiguracion
98. [x] Agregar trait `HasFilterScopes` a AsistenciaConfiguracion
99. [x] Definir `$guarded` o `$fillable` en AsistenciaConfiguracion
100. [x] Definir `$casts` (fechas, decimales, boolean) en AsistenciaConfiguracion
101. [x] Crear relación `curso()` → BelongsTo Curso (nullable) en AsistenciaConfiguracion
102. [x] Crear relación `modulo()` → BelongsTo Modulo (nullable) en AsistenciaConfiguracion
103. [x] Crear scope `scopeVigente($query, $fecha = null)` en AsistenciaConfiguracion
104. [x] Crear scope `scopeByCurso($query, $cursoId)` en AsistenciaConfiguracion
105. [x] Crear scope `scopeByModulo($query, $moduloId)` en AsistenciaConfiguracion
106. [x] Crear método `esVigente($fecha = null): bool` en AsistenciaConfiguracion
107. [x] Crear método `aplicarA($cursoId, $moduloId = null): bool` en AsistenciaConfiguracion
108. [x] Crear método estático `obtenerPara($cursoId, $moduloId = null, $fecha = null)` en AsistenciaConfiguracion
109. [x] Abrir modelo: `app/Models/Academico/Ciclo.php`
110. [x] Agregar scope `scopeActivosVigentes($query)` en modelo Ciclo
111. [x] Abrir request: `app/Http/Requests/Api/StoreAsistenciaRequest.php`
112. [x] Mover StoreAsistenciaRequest a namespace `App\Http\Requests\Api\Academico` o actualizar namespace
113. [x] Configurar método `authorize()` para retornar `true` en StoreAsistenciaRequest
114. [x] Agregar regla de validación `estudiante_id`: required, integer, exists:users,id en StoreAsistenciaRequest
115. [x] Agregar regla de validación `clase_programada_id`: required, integer, exists:asistencia_clases_programadas,id en StoreAsistenciaRequest
116. [x] Agregar regla de validación `estado`: required, enum: presente,ausente,justificado,tardanza en StoreAsistenciaRequest
117. [x] Agregar regla de validación `hora_registro`: nullable, date_format:H:i:s en StoreAsistenciaRequest
118. [x] Agregar regla de validación `observaciones`: nullable, string, max:5000 en StoreAsistenciaRequest
119. [x] Agregar mensajes personalizados en StoreAsistenciaRequest
120. [x] Agregar método `prepareForValidation()` si es necesario en StoreAsistenciaRequest
121. [x] Abrir request: `app/Http/Requests/Api/UpdateAsistenciaRequest.php`
122. [x] Mover UpdateAsistenciaRequest a namespace `App\Http\Requests\Api\Academico` o actualizar namespace
123. [x] Configurar método `authorize()` para retornar `true` en UpdateAsistenciaRequest
124. [x] Agregar regla de validación `estado`: sometimes, enum en UpdateAsistenciaRequest
125. [x] Agregar regla de validación `hora_registro`: nullable, date_format en UpdateAsistenciaRequest
126. [x] Agregar regla de validación `observaciones`: nullable, string en UpdateAsistenciaRequest
127. [x] Abrir request: `app/Http/Requests/Api/Academico/StoreAsistenciaMasivaRequest.php`
128. [x] Configurar método `authorize()` para retornar `true` en StoreAsistenciaMasivaRequest
129. [x] Agregar regla de validación `clase_programada_id`: required, integer, exists:asistencia_clases_programadas,id en StoreAsistenciaMasivaRequest
130. [x] Agregar regla de validación `asistencias`: required, array, min:1 en StoreAsistenciaMasivaRequest
131. [x] Agregar regla de validación `asistencias.*.estudiante_id`: required, integer, exists:users,id en StoreAsistenciaMasivaRequest
132. [x] Agregar regla de validación `asistencias.*.estado`: required, enum en StoreAsistenciaMasivaRequest
133. [x] Agregar regla de validación `asistencias.*.observaciones`: nullable, string en StoreAsistenciaMasivaRequest
134. [x] Abrir request: `app/Http/Requests/Api/Academico/StoreAsistenciaClaseProgramadaRequest.php`
135. [x] Configurar método `authorize()` para retornar `true` en StoreAsistenciaClaseProgramadaRequest
136. [x] Agregar regla de validación `grupo_id`: required, integer, exists:grupos,id en StoreAsistenciaClaseProgramadaRequest
137. [x] Agregar regla de validación `ciclo_id`: required, integer, exists:ciclos,id en StoreAsistenciaClaseProgramadaRequest
138. [x] Agregar regla de validación `fecha_clase`: required, date en StoreAsistenciaClaseProgramadaRequest
139. [x] Agregar regla de validación `hora_inicio`: required, date_format:H:i:s en StoreAsistenciaClaseProgramadaRequest
140. [x] Agregar regla de validación `hora_fin`: required, date_format:H:i:s en StoreAsistenciaClaseProgramadaRequest
141. [x] Agregar regla de validación `duracion_horas`: required, numeric, min:0 en StoreAsistenciaClaseProgramadaRequest
142. [x] Agregar regla de validación `estado`: sometimes, enum en StoreAsistenciaClaseProgramadaRequest
143. [x] Agregar regla de validación `observaciones`: nullable, string en StoreAsistenciaClaseProgramadaRequest
144. [x] Abrir request: `app/Http/Requests/Api/Academico/UpdateAsistenciaClaseProgramadaRequest.php`
145. [x] Configurar método `authorize()` para retornar `true` en UpdateAsistenciaClaseProgramadaRequest
146. [x] Agregar reglas de validación opcionales en UpdateAsistenciaClaseProgramadaRequest
147. [x] Abrir request: `app/Http/Requests/Api/Academico/StoreAsistenciaConfiguracionRequest.php`
148. [x] Configurar método `authorize()` para retornar `true` en StoreAsistenciaConfiguracionRequest
149. [x] Agregar regla de validación `curso_id`: nullable, integer, exists:cursos,id en StoreAsistenciaConfiguracionRequest
150. [x] Agregar regla de validación `modulo_id`: nullable, integer, exists:modulos,id en StoreAsistenciaConfiguracionRequest
151. [x] Agregar regla de validación `porcentaje_minimo`: required, numeric, min:0, max:100 en StoreAsistenciaConfiguracionRequest
152. [x] Agregar regla de validación `horas_minimas`: nullable, integer, min:0 en StoreAsistenciaConfiguracionRequest
153. [x] Agregar regla de validación `aplicar_justificaciones`: boolean en StoreAsistenciaConfiguracionRequest
154. [x] Agregar regla de validación `perder_por_fallas`: boolean en StoreAsistenciaConfiguracionRequest
155. [x] Agregar regla de validación `fecha_inicio_vigencia`: nullable, date en StoreAsistenciaConfiguracionRequest
156. [x] Agregar regla de validación `fecha_fin_vigencia`: nullable, date, after_or_equal:fecha_inicio_vigencia en StoreAsistenciaConfiguracionRequest
157. [x] Abrir request: `app/Http/Requests/Api/Academico/UpdateAsistenciaConfiguracionRequest.php`
158. [x] Configurar método `authorize()` para retornar `true` en UpdateAsistenciaConfiguracionRequest
159. [x] Agregar reglas de validación opcionales en UpdateAsistenciaConfiguracionRequest
160. [x] Abrir resource: `app/Http/Resources/Api/Academico/AsistenciaResource.php`
161. [x] Agregar campo `id` en AsistenciaResource
162. [x] Agregar campo `estudiante` (cuando está cargado) en AsistenciaResource
163. [x] Agregar campo `clase_programada` (cuando está cargado) en AsistenciaResource
164. [x] Agregar campo `grupo` (cuando está cargado) en AsistenciaResource
165. [x] Agregar campo `ciclo` (cuando está cargado) en AsistenciaResource
166. [x] Agregar campo `modulo` (cuando está cargado) en AsistenciaResource
167. [x] Agregar campo `curso` (cuando está cargado) en AsistenciaResource
168. [x] Agregar campo `estado` en AsistenciaResource
169. [x] Agregar campo `estado_text` (texto legible) en AsistenciaResource
170. [x] Agregar campo `hora_registro` en AsistenciaResource
171. [x] Agregar campo `observaciones` en AsistenciaResource
172. [x] Agregar campo `registrado_por` (cuando está cargado) en AsistenciaResource
173. [x] Agregar campo `fecha_registro` en AsistenciaResource
174. [x] Agregar campos `created_at`, `updated_at` en AsistenciaResource
175. [x] Abrir resource: `app/Http/Resources/Api/Academico/AsistenciaClaseProgramadaResource.php`
176. [x] Definir estructura de respuesta completa en AsistenciaClaseProgramadaResource
177. [x] Abrir resource: `app/Http/Resources/Api/Academico/AsistenciaConfiguracionResource.php`
178. [x] Definir estructura de respuesta completa en AsistenciaConfiguracionResource
179. [x] Abrir resource: `app/Http/Resources/Api/Academico/ListaAsistenciaResource.php`
180. [x] Definir estructura para respuesta de lista de asistencia (grupo + estudiantes + clases) en ListaAsistenciaResource
181. [x] Abrir controlador: `app/Http/Controllers/Api/Academico/AsistenciaController.php`
182. [x] Agregar middleware de permisos en constructor de AsistenciaController
183. [x] Implementar método `index()` - Listar asistencias con filtros en AsistenciaController
184. [x] Implementar método `store()` - Crear asistencia individual en AsistenciaController
185. [x] Implementar método `storeMasivo()` - Crear asistencias masivas en AsistenciaController
186. [x] Implementar método `show()` - Mostrar asistencia específica en AsistenciaController
187. [x] Implementar método `update()` - Actualizar asistencia en AsistenciaController
188. [x] Implementar método `destroy()` - Eliminar asistencia (soft delete) en AsistenciaController
189. [x] Implementar método `restore()` - Restaurar asistencia en AsistenciaController
190. [x] Implementar método `listaAsistencia()` - Obtener lista de asistencia por grupo (ciclos activos) en AsistenciaController
191. [x] Implementar método `reporteEstudiante()` - Reporte por estudiante en AsistenciaController
192. [x] Implementar método `reporteGrupo()` - Reporte por grupo en AsistenciaController
193. [x] Abrir controlador: `app/Http/Controllers/Api/Academico/AsistenciaClaseProgramadaController.php`
194. [x] Agregar middleware de permisos en constructor de AsistenciaClaseProgramadaController
195. [x] Implementar método `index()` - Listar clases programadas en AsistenciaClaseProgramadaController
196. [x] Implementar método `store()` - Crear clase programada manualmente en AsistenciaClaseProgramadaController
197. [x] Implementar método `generarAutomaticas()` - Generar clases automáticamente en AsistenciaClaseProgramadaController
198. [x] Implementar método `show()` - Mostrar clase específica en AsistenciaClaseProgramadaController
199. [x] Implementar método `update()` - Actualizar clase en AsistenciaClaseProgramadaController
200. [x] Implementar método `destroy()` - Eliminar clase en AsistenciaClaseProgramadaController
201. [x] Abrir controlador: `app/Http/Controllers/Api/Academico/AsistenciaConfiguracionController.php`
202. [x] Agregar middleware de permisos en constructor de AsistenciaConfiguracionController
203. [x] Implementar método `index()` - Listar configuraciones en AsistenciaConfiguracionController
204. [x] Implementar método `store()` - Crear configuración en AsistenciaConfiguracionController
205. [x] Implementar método `show()` - Mostrar configuración en AsistenciaConfiguracionController
206. [x] Implementar método `update()` - Actualizar configuración en AsistenciaConfiguracionController
207. [x] Implementar método `destroy()` - Eliminar configuración en AsistenciaConfiguracionController
208. [x] Crear archivo: `app/Services/Asistencia/GenerarClasesProgramadasService.php`
209. [x] Implementar método `generarParaGrupoCiclo($grupoId, $cicloId)` en GenerarClasesProgramadasService
210. [x] Implementar lógica basada en fechas del grupo en el ciclo en GenerarClasesProgramadasService
211. [x] Implementar lógica basada en horarios del grupo en GenerarClasesProgramadasService
212. [x] Implementar validación para evitar clases duplicadas en GenerarClasesProgramadasService
213. [x] Crear método `obtenerEstudiantesParaAsistencia($grupoId)` en AsistenciaController o servicio
214. [x] Implementar búsqueda de ciclos activos que contienen el grupo en obtenerEstudiantesParaAsistencia
215. [x] Implementar obtención de matrículas activas de esos ciclos en obtenerEstudiantesParaAsistencia
216. [x] Implementar retorno de estudiantes únicos con información del ciclo en obtenerEstudiantesParaAsistencia
217. [x] Crear archivo: `app/Services/Asistencia/CalcularPorcentajeAsistenciaService.php`
218. [x] Implementar método `porModulo($estudianteId, $grupoId, $cicloId)` en CalcularPorcentajeAsistenciaService
219. [x] Implementar método `porCurso($estudianteId, $cursoId)` en CalcularPorcentajeAsistenciaService
220. [x] Implementar método `general($estudianteId)` en CalcularPorcentajeAsistenciaService
221. [x] Implementar consideración de justificaciones según configuración en CalcularPorcentajeAsistenciaService
222. [x] Crear archivo: `app/Services/Asistencia/VerificarCumplimientoService.php`
223. [x] Implementar método `verificar($estudianteId, $cursoId, $moduloId = null)` en VerificarCumplimientoService
224. [x] Implementar obtención de configuración vigente en VerificarCumplimientoService
225. [x] Implementar cálculo de porcentaje en VerificarCumplimientoService
226. [x] Implementar comparación con mínimo en VerificarCumplimientoService
227. [x] Implementar retorno de resultado en VerificarCumplimientoService
228. [x] Abrir archivo: `routes/academico.php`
229. [x] Agregar ruta `Route::apiResource('asistencias', AsistenciaController::class)` en routes/academico.php
230. [x] Agregar ruta `Route::post('asistencias/masivo', [AsistenciaController::class, 'storeMasivo'])` en routes/academico.php
231. [x] Agregar ruta `Route::get('asistencias/lista-asistencia', [AsistenciaController::class, 'listaAsistencia'])` en routes/academico.php
232. [x] Agregar ruta `Route::get('asistencias/reporte/estudiante/{id}', [AsistenciaController::class, 'reporteEstudiante'])` en routes/academico.php
233. [x] Agregar ruta `Route::get('asistencias/reporte/grupo/{grupoId}', [AsistenciaController::class, 'reporteGrupo'])` en routes/academico.php
234. [x] Agregar ruta `Route::apiResource('asistencia-clases-programadas', AsistenciaClaseProgramadaController::class)` en routes/academico.php
235. [x] Agregar ruta `Route::post('asistencia-clases-programadas/generar-automaticas', [AsistenciaClaseProgramadaController::class, 'generarAutomaticas'])` en routes/academico.php
236. [x] Agregar ruta `Route::apiResource('asistencia-configuraciones', AsistenciaConfiguracionController::class)` en routes/academico.php
237. [x] Abrir seeder: `database/seeders/AsistenciaSeeder.php`
238. [x] Implementar lógica para crear asistencias de prueba en AsistenciaSeeder
239. [x] Usar datos reales de matrículas, grupos y ciclos en AsistenciaSeeder
240. [x] Abrir seeder: `database/seeders/AsistenciaConfiguracionSeeder.php`
241. [x] Crear configuración por defecto (80% mínimo) en AsistenciaConfiguracionSeeder
242. [x] Crear configuraciones por curso si es necesario en AsistenciaConfiguracionSeeder
243. [x] Abrir seeder: `database/seeders/AsistenciaClaseProgramadaSeeder.php`
244. [x] Implementar lógica para crear clases programadas de prueba en AsistenciaClaseProgramadaSeeder (opcional)
245. [x] Abrir factory: `database/factories/Academico/AsistenciaClaseProgramadaFactory.php`
246. [x] Definir estados para diferentes escenarios en AsistenciaClaseProgramadaFactory
247. [x] Configurar relaciones con grupo y ciclo en AsistenciaClaseProgramadaFactory
248. [x] Abrir o crear factory: `database/factories/Academico/AsistenciaFactory.php`
249. [x] Definir estados: presente, ausente, justificado, tardanza en AsistenciaFactory
250. [x] Configurar relaciones en AsistenciaFactory
251. [x] Abrir factory: `database/factories/Academico/AsistenciaConfiguracionFactory.php`
252. [x] Definir estados para diferentes configuraciones en AsistenciaConfiguracionFactory
253. [x] Abrir seeder: `database/seeders/RolesAndPermissionsSeeder.php`
254. [x] Agregar permiso `aca_asistencias` - Ver asistencias en RolesAndPermissionsSeeder
255. [x] Agregar permiso `aca_asistenciaCrear` - Crear asistencia en RolesAndPermissionsSeeder
256. [x] Agregar permiso `aca_asistenciaEditar` - Editar asistencia en RolesAndPermissionsSeeder
257. [x] Agregar permiso `aca_asistenciaInactivar` - Eliminar asistencia en RolesAndPermissionsSeeder
258. [x] Agregar permiso `aca_asistenciaReportes` - Ver reportes en RolesAndPermissionsSeeder
259. [x] Agregar permiso `aca_claseProgramar` - Programar clases en RolesAndPermissionsSeeder
260. [x] Agregar permiso `aca_configuracionAsistencia` - Configurar topes mínimos en RolesAndPermissionsSeeder
261. [ ] Ejecutar todas las migraciones y verificar que funcionan correctamente
262. [ ] Probar registro de asistencia individual manualmente
263. [ ] Probar registro masivo manualmente
264. [ ] Probar lista de asistencia (solo grupo, ciclos activos) manualmente
265. [ ] Probar generación automática de clases manualmente
266. [ ] Probar cálculo de porcentajes manualmente
267. [ ] Probar reportes manualmente
268. [ ] Probar configuración de topes mínimos manualmente

---

## 📝 Notas Importantes

### Cambios Clave Implementados:

1. ✅ **Nomenclatura**: Todos los modelos/tablas inician con "Asistencia"
2. ✅ **Registro simplificado**: Solo requiere grupo, busca ciclos activos automáticamente
3. ✅ **Ciclos activos**: Se filtran por `status = 1` y fechas vigentes (`fecha_inicio <= hoy <= fecha_fin`)

### Archivos Existentes a Actualizar:

-   `app/Models/Academico/Asistencia.php` - Modelo básico, necesita desarrollo completo
-   `database/migrations/2025_11_29_201012_create_asistencias_table.php` - Migración básica, necesita campos
-   `app/Http/Controllers/Api/Academico/AsistenciaController.php` - Controlador básico, necesita implementación
-   `app/Http/Requests/Api/StoreAsistenciaRequest.php` - Request básico, necesita validaciones
-   `app/Http/Requests/Api/UpdateAsistenciaRequest.php` - Request básico, necesita validaciones
-   `app/Http/Resources/Api/Academico/AsistenciaResource.php` - Resource básico, necesita estructura
-   `database/seeders/AsistenciaSeeder.php` - Seeder básico, necesita lógica

---

**Última actualización**: 2025-01-XX  
**Versión**: 3.0 (Lista completamente plana y consecutiva)
