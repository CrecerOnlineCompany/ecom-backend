import{C as K,r as v,p as D,j as Q,b as X,o as N,d as _,e as t,t as d,k as x,g as E,f as Z,w as ee,n as te}from"./vendor-d71df2f6.js";import{p as ae}from"./paymentService-51ce5170.js";import{_ as se,u as ne}from"./index-3ede1e9e.js";const I={async printThermalTicket(l){try{const s=window.open("","","width=400,height=600");if(!s)return console.error("No se pudo abrir la ventana de impresión"),!1;const u=this.generateThermalHTML(l);return s.document.write(u),s.document.close(),s.onload=()=>{s.print(),setTimeout(()=>{s.close()},1e3)},!0}catch(s){return console.error("Error al imprimir:",s),!1}},async printMultipleTickets(l){try{const s=window.open("","","width=400,height=600");if(!s)return console.error("No se pudo abrir la ventana de impresión"),!1;let u=`
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="UTF-8">
          <style>
            ${this.getThermalStyles()}
          </style>
        </head>
        <body>
      `;return l.forEach((w,p)=>{u+=this.generateThermalTicketContent(w),p<l.length-1&&(u+='<div style="page-break-after: always; margin-top: 20px;"></div>')}),u+="</body></html>",s.document.write(u),s.document.close(),s.onload=()=>{s.print(),setTimeout(()=>{s.close()},1e3)},!0}catch(s){return console.error("Error al imprimir múltiples tickets:",s),!1}},generateThermalHTML(l){const s=this.generateThermalTicketContent(l);return`
      <!DOCTYPE html>
      <html>
      <head>
        <meta charset="UTF-8">
        <style>
          ${this.getThermalStyles()}
        </style>
      </head>
      <body>
        ${s}
      </body>
      </html>
    `},generateThermalTicketContent(l){const u=new Date().toLocaleDateString("es-ES",{day:"2-digit",month:"2-digit",year:"numeric",hour:"2-digit",minute:"2-digit"});return`
      <div class="thermal-ticket">
        <div class="thermal-header">
          <div class="cinema-name">CINEA</div>
          <div class="cinema-subtitle">Cines Independientes</div>
        </div>

        <div class="thermal-divider">═══════════════════════════</div>

        <div class="thermal-section">
          <div class="thermal-label">PELÍCULA:</div>
          <div class="thermal-value bold">${this.truncateText(l.movieTitle||"N/A",32)}</div>
        </div>

        <div class="thermal-section">
          <div class="thermal-row">
            <div class="thermal-col">
              <div class="thermal-label">FECHA:</div>
              <div class="thermal-value">${l.screeningDate||"N/A"}</div>
            </div>
            <div class="thermal-col">
              <div class="thermal-label">HORA:</div>
              <div class="thermal-value">${l.screeningTime||"N/A"}</div>
            </div>
          </div>
        </div>

        <div class="thermal-section">
          <div class="thermal-label">ASIENTO:</div>
          <div class="thermal-value bold">${l.seatNumber||"N/A"}</div>
        </div>

        <div class="thermal-section">
          <div class="thermal-label">ENTRADA:</div>
          <div class="thermal-barcode">${l.ticketNumber||"N/A"}</div>
          <div class="thermal-code-small">${l.ticketNumber||"N/A"}</div>
        </div>

        <div class="thermal-section">
          <div class="thermal-label">PRECIO:</div>
          <div class="thermal-value bold">${l.price||"0.00"} €</div>
        </div>

        <div class="thermal-divider">═══════════════════════════</div>

        <div class="thermal-footer">
          <div class="thermal-small">Impreso: ${u}</div>
          <div class="thermal-small">Presenta este código en la entrada</div>
          <div class="thermal-small">Válido solo para la función indicada</div>
        </div>

        <div class="thermal-divider">═══════════════════════════</div>
      </div>
    `},getThermalStyles(){return`
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }

      body {
        font-family: 'Courier New', monospace;
        width: 80mm;
        padding: 0;
        margin: 0;
        background: white;
      }

      @page {
        size: 80mm 200mm;
        margin: 0;
        padding: 0;
      }

      .thermal-ticket {
        width: 100%;
        padding: 2mm 2mm;
        text-align: center;
        font-size: 10pt;
        line-height: 1.3;
      }

      .thermal-header {
        margin-bottom: 5mm;
        padding-bottom: 3mm;
        border-bottom: 1px solid #000;
      }

      .cinema-name {
        font-size: 18pt;
        font-weight: bold;
        letter-spacing: 2px;
        margin-bottom: 2mm;
      }

      .cinema-subtitle {
        font-size: 8pt;
        color: #333;
      }

      .thermal-divider {
        text-align: center;
        font-size: 9pt;
        margin: 3mm 0;
        letter-spacing: 1px;
      }

      .thermal-section {
        margin: 3mm 0;
        text-align: left;
        padding: 0 2mm;
      }

      .thermal-label {
        font-size: 8pt;
        font-weight: bold;
        color: #333;
        margin-bottom: 1mm;
      }

      .thermal-value {
        font-size: 10pt;
        word-break: break-word;
      }

      .thermal-value.bold {
        font-weight: bold;
        font-size: 11pt;
      }

      .thermal-row {
        display: flex;
        gap: 5mm;
      }

      .thermal-col {
        flex: 1;
      }

      .thermal-barcode {
        font-family: 'Code 128', 'Courier New', monospace;
        font-size: 20pt;
        font-weight: bold;
        letter-spacing: 2px;
        margin: 2mm 0;
        word-break: break-all;
      }

      .thermal-code-small {
        font-size: 8pt;
        letter-spacing: 1px;
        word-break: break-all;
      }

      .thermal-footer {
        margin-top: 3mm;
        padding-top: 2mm;
        border-top: 1px solid #000;
      }

      .thermal-small {
        font-size: 8pt;
        color: #555;
        margin: 1mm 0;
      }

      @media print {
        body {
          margin: 0;
          padding: 0;
        }
        .thermal-ticket {
          page-break-after: always;
        }
      }
    `},truncateText(l,s){return l.length<=s?l:l.substring(0,s-3)+"..."},getTicketData(l){const s=localStorage.getItem("tickets");if(s)try{const w=JSON.parse(s).find(p=>p.ticketNumber===l);if(w)return w}catch(u){console.error("Error al leer tickets del localStorage:",u)}return{ticketNumber:l,movieTitle:"Película",screeningDate:new Date().toLocaleDateString("es-ES"),screeningTime:"20:00",seatNumber:"N/A",price:"10.00"}}};const re={class:"payment-success-page"},le={class:"container"},ie={class:"success-card"},oe={key:0,class:"loading-message"},de={key:1,class:"loading-error"},ue={key:2,class:"desk-assistance"},ce={key:3,class:"ticket-display"},me={class:"ticket-section"},ve={class:"info-grid"},pe={class:"info-item"},ge={class:"info-value code"},be={class:"info-item"},he={class:"info-value"},fe={class:"info-item"},ye={class:"info-value"},Ne={class:"info-item"},_e={class:"info-value"},Ae={class:"info-item"},ke={class:"info-value"},we={class:"info-item"},Se={class:"info-value"},Te={class:"purchase-details"},Ce={class:"detail-row"},De={class:"code"},Ee={class:"detail-row"},Pe={class:"code"},xe={class:"detail-row"},Ie={class:"detail-row"},Re={class:"amount"},ze={class:"detail-row"},$e={class:"status-badge status-success"},Oe={class:"next-steps"},Le={class:"action-buttons"},Fe=["disabled"],Me={key:0},Ue={key:1},Ve=["disabled"],je={__name:"PaymentSuccess",setup(l){const s=K(),u=ne(),w=v(""),p=v(""),i=v(null),y=v([]),P=v(!1),R=v(!1),S=v(""),g=v(""),h=v("info"),A=v(null),k=v(null),b=v(!1),O=a=>{if(!a)return"N/A";const e=new Date(a);return Number.isNaN(e.getTime())?"N/A":e.toLocaleDateString("es-AR")},L=a=>{if(!a)return"N/A";const e=new Date(a);return Number.isNaN(e.getTime())?"N/A":e.toLocaleTimeString("es-AR",{hour:"2-digit",minute:"2-digit"})},F=(a,e)=>{var o,C,m,f;const r=(Array.isArray(a==null?void 0:a.details)?a.details:[]).map(c=>(c==null?void 0:c.seat_code)||`${(c==null?void 0:c.row_number)||""}${(c==null?void 0:c.seat_number)||""}`).filter(Boolean);return{ticketNumber:String((a==null?void 0:a.ticket_number)||(a==null?void 0:a.id)||"N/A"),movieTitle:String(((C=(o=e==null?void 0:e.screening)==null?void 0:o.movie)==null?void 0:C.title)||"Película"),screeningDate:O((m=e==null?void 0:e.screening)==null?void 0:m.start_time),screeningTime:L((f=e==null?void 0:e.screening)==null?void 0:f.start_time),seatNumber:r.length?r.join(", "):"N/A",price:String((a==null?void 0:a.price)||"0.00")}},M=a=>{const e=String(a||"").toLowerCase();return e==="completed"||e==="approved"?"Completado":e==="processing"||e==="pending"?"Procesando":e==="rejected"||e==="declined"?"Rechazado":"Completado"},U=D(()=>{var n,r;const a=((n=k.value)==null?void 0:n.paidAt)||((r=k.value)==null?void 0:r.updatedAt);return(a?new Date(a):new Date).toLocaleDateString("es-ES",{day:"2-digit",month:"2-digit",year:"numeric",hour:"2-digit",minute:"2-digit"})}),T=D(()=>{var a;return((a=A.value)==null?void 0:a.orderNumber)||p.value||"N/A"}),V=D(()=>{var e,n;const a=Number(((e=k.value)==null?void 0:e.amount)??((n=A.value)==null?void 0:n.totalAmount));return Number.isFinite(a)?a.toFixed(2):"No disponible"}),z=D(()=>{var a,e;return((a=k.value)==null?void 0:a.currency)||((e=A.value)==null?void 0:e.currency)||"ARS"}),j=D(()=>{var a;return M((a=k.value)==null?void 0:a.status)}),$=a=>String(a||"").trim(),q=a=>{var r,o;const e=$((r=u.currentSession)==null?void 0:r.order_number),n=$(a||p.value||((o=A.value)==null?void 0:o.orderNumber));!e||!n||e===n&&(u.clearCart(),u.clearPaymentSession())};Q(async()=>{var a;if(w.value=s.query.ticket||s.params.ticket||"N/A",p.value=s.query.order||s.params.order||"",A.value={orderNumber:String(p.value||"N/A"),currency:"ARS"},!p.value){b.value=!0,S.value="Falta el número de orden en la URL. Continúa en ventanilla.";return}await J(),q((a=A.value)==null?void 0:a.orderNumber),window.scrollTo(0,0)});const H=(a=[])=>{if(a.length)try{const e=localStorage.getItem("tickets"),n=e?JSON.parse(e):[],r=new Map(n.map(o=>[o.ticketNumber,o]));for(const o of a)o!=null&&o.ticketNumber&&r.set(o.ticketNumber,o);localStorage.setItem("tickets",JSON.stringify(Array.from(r.values())))}catch(e){console.warn("No se pudo persistir tickets en localStorage:",e)}},B=async()=>{if(!y.value.length&&!i.value)return;const a=y.value.length?y.value:[i.value];let e=!1;a.length>1?e=await I.printMultipleTickets(a):e=await I.printThermalTicket(a[0]),e?(h.value="success",g.value="Impresión automática iniciada."):(h.value="error",g.value='No se pudo imprimir automáticamente. Usa el botón "Imprimir Entrada".')},J=async()=>{var a;R.value=!0,S.value="",b.value=!1;try{const n=await ae.getPaymentOrderDetails(p.value)||{};if(!n.success||!n.order)throw new Error("Respuesta inválida del endpoint de detalle de orden");const r=n.order,o=Array.isArray(n.tickets)?n.tickets:[],m=(Array.isArray(n.payments)?n.payments:[])[0]||{},f=Number(r.total_amount??((a=m==null?void 0:m.response_data)==null?void 0:a.amount));if(!Number.isFinite(f))throw new Error("Monto no disponible desde backend");if(A.value={orderNumber:String(r.order_number||p.value),currency:String(r.currency||"ARS"),totalAmount:f},k.value={amount:f,status:m.status||r.status||"completed",paidAt:r.paid_at||m.completed_at||null,updatedAt:m.updated_at||r.updated_at||null,currency:String(r.currency||"ARS")},o.length===0){b.value=!0,S.value="Pago confirmado, pero las entradas aún no están disponibles. Continúa en ventanilla con tu número de orden.",i.value=null,y.value=[],h.value="error",g.value="Impresión deshabilitada: ve a ventanilla con tu número de orden.";return}const c=o.map(G=>F(G,r));y.value=c,i.value=c[0],H(c),await B()}catch(e){console.error("Error al cargar datos del ticket:",e),S.value="Faltan datos del pago o el backend no respondió. Continúa en ventanilla.",b.value=!0,i.value=null,y.value=[],k.value=null,h.value="error",g.value="Impresión deshabilitada: ve a ventanilla con tu número de orden."}finally{R.value=!1}},W=async()=>{if(b.value){h.value="error",g.value="Impresión deshabilitada: ve a ventanilla con tu número de orden.";return}P.value=!0;try{const a=y.value.length?y.value:[i.value];(a.length>1?await I.printMultipleTickets(a):await I.printThermalTicket(a[0]))?(h.value="success",g.value="Impresión iniciada correctamente."):(h.value="error",g.value="No se pudo completar la impresión. Verifica tu impresora.")}catch(a){console.error("Error durante la impresión:",a),h.value="error",g.value="Error al intentar imprimir. Intenta de nuevo."}finally{P.value=!1}},Y=()=>{var o,C,m,f;if(!i.value||b.value)return;const a=`
    <div style="text-align: center; padding: 20px; font-family: Arial, sans-serif;">
      <h1>CINEA - Entrada</h1>
      <p><strong>${((o=i.value)==null?void 0:o.movieTitle)||"Película"}</strong></p>
      <p>Número de orden: ${T.value}</p>
      <p>Fecha: ${((C=i.value)==null?void 0:C.screeningDate)||"N/A"}</p>
      <p>Hora: ${((m=i.value)==null?void 0:m.screeningTime)||"N/A"}</p>
      <p>Asiento: ${((f=i.value)==null?void 0:f.seatNumber)||"N/A"}</p>
    </div>
  `,e=new Blob([a],{type:"text/html"}),n=window.URL.createObjectURL(e),r=document.createElement("a");r.href=n,r.download=`entrada_${T.value}.html`,r.click(),window.URL.revokeObjectURL(n)};return(a,e)=>{const n=X("router-link");return N(),_("div",re,[t("div",le,[t("div",ie,[e[24]||(e[24]=t("div",{class:"success-header"},[t("div",{class:"success-icon-large icon-success"}),t("h1",null,"¡Pago Exitoso!"),t("p",{class:"success-subtitle"},"Tu compra ha sido procesada correctamente")],-1)),R.value?(N(),_("p",oe,"Cargando información de la compra...")):S.value?(N(),_("p",de,d(S.value),1)):x("",!0),b.value?(N(),_("div",ue,[e[1]||(e[1]=t("h3",null,"Atención en ventanilla requerida",-1)),e[2]||(e[2]=t("p",null,"No pudimos validar todos los datos del pago en línea.",-1)),t("p",null,[e[0]||(e[0]=t("strong",null,"Orden:",-1)),E(" "+d(T.value),1)]),e[3]||(e[3]=t("p",null,"Acércate a ventanilla con este número para finalizar la entrega de entradas.",-1))])):x("",!0),i.value&&!b.value?(N(),_("div",ce,[t("div",me,[e[10]||(e[10]=t("h3",null,"Información de la Entrada",-1)),t("div",ve,[t("div",pe,[e[4]||(e[4]=t("span",{class:"info-label"},"Número de Entrada:",-1)),t("span",ge,d(i.value.ticketNumber),1)]),t("div",be,[e[5]||(e[5]=t("span",{class:"info-label"},"Película:",-1)),t("span",he,d(i.value.movieTitle),1)]),t("div",fe,[e[6]||(e[6]=t("span",{class:"info-label"},"Fecha:",-1)),t("span",ye,d(i.value.screeningDate),1)]),t("div",Ne,[e[7]||(e[7]=t("span",{class:"info-label"},"Hora:",-1)),t("span",_e,d(i.value.screeningTime),1)]),t("div",Ae,[e[8]||(e[8]=t("span",{class:"info-label"},"Asiento:",-1)),t("span",ke,d(i.value.seatNumber),1)]),t("div",we,[e[9]||(e[9]=t("span",{class:"info-label"},"Precio:",-1)),t("span",Se,d(i.value.price)+" "+d(z.value),1)])])])])):x("",!0),t("div",Te,[e[16]||(e[16]=t("h3",null,"Detalles de la Compra",-1)),t("div",Ce,[e[11]||(e[11]=t("span",null,"Número de Orden:",-1)),t("span",De,d(T.value),1)]),t("div",Ee,[e[12]||(e[12]=t("span",null,"Código de Compra:",-1)),t("span",Pe,d(T.value),1)]),t("div",xe,[e[13]||(e[13]=t("span",null,"Fecha de Compra:",-1)),t("span",null,d(U.value),1)]),t("div",Ie,[e[14]||(e[14]=t("span",null,"Importe Pagado:",-1)),t("span",Re,d(V.value)+" "+d(z.value),1)]),t("div",ze,[e[15]||(e[15]=t("span",null,"Estado:",-1)),t("span",$e,d(j.value),1)])]),t("div",Oe,[e[22]||(e[22]=t("h3",null,"Próximos Pasos",-1)),t("ol",null,[t("li",null,[e[17]||(e[17]=E("Recuerda tu número de orden: ",-1)),t("strong",null,d(T.value),1)]),e[18]||(e[18]=t("li",null,"Recibirás un email de confirmación con tu comprobante",-1)),e[19]||(e[19]=t("li",null,"Puedes imprimir tu entrada desde el botón de abajo",-1)),e[20]||(e[20]=t("li",null,"Presenta tu entrada en la taquilla del cine",-1)),e[21]||(e[21]=t("li",null,"Llega 15 minutos antes de que comience la función",-1))])]),t("div",Le,[t("button",{onClick:W,class:"btn btn-primary btn-large",disabled:P.value||b.value||!i.value},[P.value?(N(),_("span",Ue,"Imprimiendo...")):(N(),_("span",Me,"Imprimir Entrada"))],8,Fe),t("button",{onClick:Y,class:"btn btn-secondary btn-large",disabled:b.value||!i.value}," Descargar Entrada ",8,Ve),Z(n,{to:"/",class:"btn btn-tertiary btn-large"},{default:ee(()=>[...e[23]||(e[23]=[E(" Volver al Inicio ",-1)])]),_:1})]),g.value?(N(),_("p",{key:4,class:te(["print-message",h.value])},d(g.value),3)):x("",!0),e[25]||(e[25]=t("div",{class:"contact-section"},[t("h4",null,"¿Necesitas ayuda?"),t("p",null,[E("Contacta con nosotros en "),t("strong",null,"info@cinea.es")]),t("p",null,[E("Teléfono: "),t("strong",null,"+34 91 123 4567")]),t("p",{class:"hours"},"Lun-Dom: 10:00 - 22:00")],-1))])]),e[26]||(e[26]=t("div",{id:"print-frame",style:{display:"none"}},null,-1))])}}},Je=se(je,[["__scopeId","data-v-2f510915"]]);export{Je as default};
