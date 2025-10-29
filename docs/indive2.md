Diseño de indicadores clave y personalizable

En su mayoría los indicadores son una relación entre dos variables y según esa relación se calculan porcentajes o índices,

Entonces debe existir dentro del cálculo del indicador:

Desde el backend

1. Modelo desde donde viene el respectivo indicador, este modelo también servirá para organizar los indicadores al momento de hacer las consultas, se usará el archivo config/kpis.php para diseñar esta parte.  
2. Según el modelo elegido se podrá definir el Numerador requerido.  
3. Según el modelo elegido se podrá definir el Denominador.  
4. Numerador y denominador podrían ser de modelos diferentes (los definidos en config/kpis.php).  
5. Forma de obtención de los datos (Conteo, agrupación, promedio, suma, etc) por cada uno \- numerador, denominador \-. Esto se debe proveer en un array para estandarizar la obtención de esta información y se enviará junto con el endpoint que entrega la información de los modelos a utilizar. Ver la posibilidad de incluirlo en el archivo config/kpis.php  
6. Factor de Cálculo (\*1, \*100, \*1000), su valor por defecto será 1\.  
7. Diseñar el servicio necesario para establecer la obtención y cálculo del numerador y denominador. En ese mismo servicio generar el cálculo del indicador \- KPI (numerador / denominador) \* factor de cálculo en el periodo definido.   
8. Meta del indicador debe ser nullable, en ocasiones el indicador solo muestra algo, si es un KPI si tiene meta (Esto se deja como una anotación).  
9. Periodo de evaluación (día, semana, mes, trimestre, semestre).  
10. Para validar las fechas se tomará por defecto el campo ‘created\_at’, pero debe existir la posibilidad de elegir un campo específico de fecha de control al momento de crear el indicador, basado en el modulo aplicable.  
11. Para mostrar una evolución por defecto tomará como muestra el siguiente período de control por ejemplo si está calculando diario, tomará los datos para una semana, o si es para una semana tomará los datos de un mes, etc.  
12. Si no hay definición de período por parte del usuario, tomará como base la fecha inmediatamente anterior al día de consulta y hará una regresión de tiempo con base en lo definido en el punto anterior.  
13. El tiempo lo podrá definir el usuario, pero si define un tiempo igual al establecido por el indicador y más corto que el parámetro, por defecto aplicará la regla del item 8, esta información deberá recibirse y ajustarse en el frontend en tiempo real.  
14. Definición y Json con el esquema del gráfico a mostrar.  
15. Si tiene meta deberá verse como una línea en el gráfico.  
16. Los endpoints que se generen deberán enviar los datos estructurados para que los tome "vue-echarts": "^6.0.2" y "echarts": "^5.3.1" en el front y muestre los resultados, eso indica que al momento de crear el indicador y seleccionar el gráfico deberá existir una plantilla de como se debe estructurar según el tipo de gráfico para no tener errores en el frontend  
17. Se generarán dashboard que contendrán las dashboardCard encargadas de mostrar los indicadores, el usuario podrá generar tantos como requiera. Generar su CRUD completo.  
18. Las dashboard serán de vista general o de vista específica, eso quiere decir que un usuario podrá generar un dashboard con ciertos indicadores aplicables a todos. Pero a su vez, los usuarios podrán crear sus propios dashboards a los que solo él podrá ver. En cualquier caso el Superusuario del sistema podrá ver todos los dashboards definidos.  
19. Las dashboardCard se encargan de gestionar un único indicador, este modelo guardará solamente los datos de gestión de la misma dashboardCard y la relación con su indicador respectivo, podrá cambiar su fondo, su tamaño y su orden de presentación dentro de la dashboard.  
20. Dado que el dashboardCard podrá cambiar su aspecto y ubicación en tiempo real, sus datos deberán actualizarse también en tiempo real en el endpoint respectivo.  
21. Desde el dashboardCard se recibirá el cambio de los períodos, el endpoint que muestre el indicador deberá estar en la capacidad de hacerlo y recalcular el indicador en tiempo real.  
22. Establecer los Request, Resource necesarios para facilitar la validación de los datos enviados y recibidos tanto de KPI como de dashboard y dashboardCard.  
23. El dashboard que esté viendo el usuario se podrá descargar en PDF y enviar por correo, se deberá proveer lo necesario para que esta parte funcione.  
24. Los correos se podrán enviar a usuarios registrados en el sistema y no registrados también (correos digitados a mano).  
25. Ver la posibilidad de conservar los correos a los cuales normalmente hace los envíos para facilitar esta tarea (algo así como generar grupos de envío por usuario).  
26. Diseñar plantilla blade en el backend para anexar el correo.  
27. El correo se enviará con un job para no detener el proceso y lo hará después de renderizar el PDF respectivo.

Desde el frontend

Se utiliza VUE para diseñarlo, las gráficas se generan con "vue-echarts": "^6.0.2" y "echarts": "^5.3.1" para hacer el drag and drop de las dashboardCard se utilizará la librería vue-grid-layout

1. Se definirá una pantalla CRUD para los indicadores, definiendo el tipo de gráfico a utilizar, deberá validar los datos requeridos para generar el gráfico elegido según el caso.  
2. El gráfico se mostrará al momento de ir generando los indicadores es decir que en una primera parte de creación del indicador se definen el numerador, denominador, factor, meta, etc y en la otra se mostrará el gráfico que se elija el cuál ira mostrándose según el caso.  
3. Se definirán dashboards para que cada usuario establezca los indicadores \- KPI que quiere analizar.  
4. Dentro de cada dashboard se definirá un dashboardCard que gestionará el respectivo indicador que vaya a mostrar (solo uno), desde allí podrá cambiar las fechas de presentación del indicador respectivo y se mostrará el gráfico respectivo.  
5. Los dashboardCard deben poder reubicarse dentro de la pantalla \- grilla (dashboard), cambiar su tamaño, cambiar su background y el gráfico deberá ajustarse a esas condiciones.  
6. La pantalla que esté viendo el usuario deberá poder descargarse en PDF con las imágenes e indicadores que esté viendo, así mismo este PDF se enviará por correo a los usuarios que este defina, tanto usuarios registrados en el sistema como no registrados.