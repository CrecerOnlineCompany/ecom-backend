import{C as W,r as v,p as D,j as Y,b as G,o as N,d as _,e as t,t as d,k as P,g as C,f as K,w as Q,n as X}from"./vendor-d71df2f6.js";import{p as Z}from"./paymentService-3cdf615a.js";import{_ as ee}from"./index-635cdea5.js";const x={async printThermalTicket(l){try{const s=window.open("","","width=400,height=600");if(!s)return console.error("No se pudo abrir la ventana de impresión"),!1;const u=this.generateThermalHTML(l);return s.document.write(u),s.document.close(),s.onload=()=>{s.print(),setTimeout(()=>{s.close()},1e3)},!0}catch(s){return console.error("Error al imprimir:",s),!1}},async printMultipleTickets(l){try{const s=window.open("","","width=400,height=600");if(!s)return console.error("No se pudo abrir la ventana de impresión"),!1;let u=`
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="UTF-8">
          <style>
            ${this.getThermalStyles()}
          </style>
        </head>
        <body>
      `;return l.forEach((p,n)=>{u+=this.generateThermalTicketContent(p),n<l.length-1&&(u+='<div style="page-break-after: always; margin-top: 20px;"></div>')}),u+="</body></html>",s.document.write(u),s.document.close(),s.onload=()=>{s.print(),setTimeout(()=>{s.close()},1e3)},!0}catch(s){return console.error("Error al imprimir múltiples tickets:",s),!1}},generateThermalHTML(l){const s=this.generateThermalTicketContent(l);return`
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
    `},truncateText(l,s){return l.length<=s?l:l.substring(0,s-3)+"..."},getTicketData(l){const s=localStorage.getItem("tickets");if(s)try{const p=JSON.parse(s).find(n=>n.ticketNumber===l);if(p)return p}catch(u){console.error("Error al leer tickets del localStorage:",u)}return{ticketNumber:l,movieTitle:"Película",screeningDate:new Date().toLocaleDateString("es-ES"),screeningTime:"20:00",seatNumber:"N/A",price:"10.00"}}};const te={class:"payment-success-page"},ae={class:"container"},se={class:"success-card"},ne={key:0,class:"loading-message"},le={key:1,class:"loading-error"},ie={key:2,class:"desk-assistance"},re={key:3,class:"ticket-display"},oe={class:"ticket-section"},de={class:"info-grid"},ue={class:"info-item"},ce={class:"info-value code"},me={class:"info-item"},ve={class:"info-value"},pe={class:"info-item"},ge={class:"info-value"},he={class:"info-item"},be={class:"info-value"},fe={class:"info-item"},ye={class:"info-value"},Ne={class:"info-item"},_e={class:"info-value"},Ae={class:"purchase-details"},ke={class:"detail-row"},we={class:"code"},Te={class:"detail-row"},Se={class:"code"},De={class:"detail-row"},Ce={class:"detail-row"},Ee={class:"amount"},Pe={class:"detail-row"},xe={class:"status-badge status-success"},Ie={class:"next-steps"},Re={class:"action-buttons"},$e=["disabled"],ze={key:0},Le={key:1},Oe=["disabled"],Fe={__name:"PaymentSuccess",setup(l){const s=W(),u=v(""),p=v(""),n=v(null),y=v([]),E=v(!1),I=v(!1),k=v(""),g=v(""),b=v("info"),S=v(null),A=v(null),h=v(!1),$=a=>{if(!a)return"N/A";const e=new Date(a);return Number.isNaN(e.getTime())?"N/A":e.toLocaleDateString("es-AR")},z=a=>{if(!a)return"N/A";const e=new Date(a);return Number.isNaN(e.getTime())?"N/A":e.toLocaleTimeString("es-AR",{hour:"2-digit",minute:"2-digit"})},L=(a,e)=>{var o,T,m,f;const r=(Array.isArray(a==null?void 0:a.details)?a.details:[]).map(c=>(c==null?void 0:c.seat_code)||`${(c==null?void 0:c.row_number)||""}${(c==null?void 0:c.seat_number)||""}`).filter(Boolean);return{ticketNumber:String((a==null?void 0:a.ticket_number)||(a==null?void 0:a.id)||"N/A"),movieTitle:String(((T=(o=e==null?void 0:e.screening)==null?void 0:o.movie)==null?void 0:T.title)||"Película"),screeningDate:$((m=e==null?void 0:e.screening)==null?void 0:m.start_time),screeningTime:z((f=e==null?void 0:e.screening)==null?void 0:f.start_time),seatNumber:r.length?r.join(", "):"N/A",price:String((a==null?void 0:a.price)||"0.00")}},O=a=>{const e=String(a||"").toLowerCase();return e==="completed"||e==="approved"?"Completado":e==="processing"||e==="pending"?"Procesando":e==="rejected"||e==="declined"?"Rechazado":"Completado"},F=D(()=>{var i,r;const a=((i=A.value)==null?void 0:i.paidAt)||((r=A.value)==null?void 0:r.updatedAt);return(a?new Date(a):new Date).toLocaleDateString("es-ES",{day:"2-digit",month:"2-digit",year:"numeric",hour:"2-digit",minute:"2-digit"})}),w=D(()=>{var a;return((a=S.value)==null?void 0:a.orderNumber)||p.value||"N/A"}),M=D(()=>{var e,i;const a=Number(((e=A.value)==null?void 0:e.amount)??((i=S.value)==null?void 0:i.totalAmount));return Number.isFinite(a)?a.toFixed(2):"No disponible"}),R=D(()=>{var a,e;return((a=A.value)==null?void 0:a.currency)||((e=S.value)==null?void 0:e.currency)||"ARS"}),U=D(()=>{var a;return O((a=A.value)==null?void 0:a.status)});Y(async()=>{if(u.value=s.query.ticket||s.params.ticket||"N/A",p.value=s.query.order||s.params.order||"",S.value={orderNumber:String(p.value||"N/A"),currency:"ARS"},!p.value){h.value=!0,k.value="Falta el número de orden en la URL. Continúa en ventanilla.";return}await q(),window.scrollTo(0,0)});const V=(a=[])=>{if(a.length)try{const e=localStorage.getItem("tickets"),i=e?JSON.parse(e):[],r=new Map(i.map(o=>[o.ticketNumber,o]));for(const o of a)o!=null&&o.ticketNumber&&r.set(o.ticketNumber,o);localStorage.setItem("tickets",JSON.stringify(Array.from(r.values())))}catch(e){console.warn("No se pudo persistir tickets en localStorage:",e)}},j=async()=>{if(!y.value.length&&!n.value)return;const a=y.value.length?y.value:[n.value];let e=!1;a.length>1?e=await x.printMultipleTickets(a):e=await x.printThermalTicket(a[0]),e?(b.value="success",g.value="Impresión automática iniciada."):(b.value="error",g.value='No se pudo imprimir automáticamente. Usa el botón "Imprimir Entrada".')},q=async()=>{var a;I.value=!0,k.value="",h.value=!1;try{const i=await Z.getPaymentOrderDetails(p.value)||{};if(!i.success||!i.order)throw new Error("Respuesta inválida del endpoint de detalle de orden");const r=i.order,o=Array.isArray(i.tickets)?i.tickets:[],m=(Array.isArray(i.payments)?i.payments:[])[0]||{},f=Number(r.total_amount??((a=m==null?void 0:m.response_data)==null?void 0:a.amount));if(!Number.isFinite(f))throw new Error("Monto no disponible desde backend");if(S.value={orderNumber:String(r.order_number||p.value),currency:String(r.currency||"ARS"),totalAmount:f},A.value={amount:f,status:m.status||r.status||"completed",paidAt:r.paid_at||m.completed_at||null,updatedAt:m.updated_at||r.updated_at||null,currency:String(r.currency||"ARS")},o.length===0){h.value=!0,k.value="Pago confirmado, pero las entradas aún no están disponibles. Continúa en ventanilla con tu número de orden.",n.value=null,y.value=[],b.value="error",g.value="Impresión deshabilitada: ve a ventanilla con tu número de orden.";return}const c=o.map(J=>L(J,r));y.value=c,n.value=c[0],V(c),await j()}catch(e){console.error("Error al cargar datos del ticket:",e),k.value="Faltan datos del pago o el backend no respondió. Continúa en ventanilla.",h.value=!0,n.value=null,y.value=[],A.value=null,b.value="error",g.value="Impresión deshabilitada: ve a ventanilla con tu número de orden."}finally{I.value=!1}},H=async()=>{if(h.value){b.value="error",g.value="Impresión deshabilitada: ve a ventanilla con tu número de orden.";return}E.value=!0;try{const a=y.value.length?y.value:[n.value];(a.length>1?await x.printMultipleTickets(a):await x.printThermalTicket(a[0]))?(b.value="success",g.value="Impresión iniciada correctamente."):(b.value="error",g.value="No se pudo completar la impresión. Verifica tu impresora.")}catch(a){console.error("Error durante la impresión:",a),b.value="error",g.value="Error al intentar imprimir. Intenta de nuevo."}finally{E.value=!1}},B=()=>{var o,T,m,f;if(!n.value||h.value)return;const a=`
    <div style="text-align: center; padding: 20px; font-family: Arial, sans-serif;">
      <h1>CINEA - Entrada</h1>
      <p><strong>${((o=n.value)==null?void 0:o.movieTitle)||"Película"}</strong></p>
      <p>Número de orden: ${w.value}</p>
      <p>Fecha: ${((T=n.value)==null?void 0:T.screeningDate)||"N/A"}</p>
      <p>Hora: ${((m=n.value)==null?void 0:m.screeningTime)||"N/A"}</p>
      <p>Asiento: ${((f=n.value)==null?void 0:f.seatNumber)||"N/A"}</p>
    </div>
  `,e=new Blob([a],{type:"text/html"}),i=window.URL.createObjectURL(e),r=document.createElement("a");r.href=i,r.download=`entrada_${w.value}.html`,r.click(),window.URL.revokeObjectURL(i)};return(a,e)=>{const i=G("router-link");return N(),_("div",te,[t("div",ae,[t("div",se,[e[24]||(e[24]=t("div",{class:"success-header"},[t("div",{class:"success-icon-large icon-success"}),t("h1",null,"¡Pago Exitoso!"),t("p",{class:"success-subtitle"},"Tu compra ha sido procesada correctamente")],-1)),I.value?(N(),_("p",ne,"Cargando información de la compra...")):k.value?(N(),_("p",le,d(k.value),1)):P("",!0),h.value?(N(),_("div",ie,[e[1]||(e[1]=t("h3",null,"Atención en ventanilla requerida",-1)),e[2]||(e[2]=t("p",null,"No pudimos validar todos los datos del pago en línea.",-1)),t("p",null,[e[0]||(e[0]=t("strong",null,"Orden:",-1)),C(" "+d(w.value),1)]),e[3]||(e[3]=t("p",null,"Acércate a ventanilla con este número para finalizar la entrega de entradas.",-1))])):P("",!0),n.value&&!h.value?(N(),_("div",re,[t("div",oe,[e[10]||(e[10]=t("h3",null,"Información de la Entrada",-1)),t("div",de,[t("div",ue,[e[4]||(e[4]=t("span",{class:"info-label"},"Número de Entrada:",-1)),t("span",ce,d(n.value.ticketNumber),1)]),t("div",me,[e[5]||(e[5]=t("span",{class:"info-label"},"Película:",-1)),t("span",ve,d(n.value.movieTitle),1)]),t("div",pe,[e[6]||(e[6]=t("span",{class:"info-label"},"Fecha:",-1)),t("span",ge,d(n.value.screeningDate),1)]),t("div",he,[e[7]||(e[7]=t("span",{class:"info-label"},"Hora:",-1)),t("span",be,d(n.value.screeningTime),1)]),t("div",fe,[e[8]||(e[8]=t("span",{class:"info-label"},"Asiento:",-1)),t("span",ye,d(n.value.seatNumber),1)]),t("div",Ne,[e[9]||(e[9]=t("span",{class:"info-label"},"Precio:",-1)),t("span",_e,d(n.value.price)+" "+d(R.value),1)])])])])):P("",!0),t("div",Ae,[e[16]||(e[16]=t("h3",null,"Detalles de la Compra",-1)),t("div",ke,[e[11]||(e[11]=t("span",null,"Número de Orden:",-1)),t("span",we,d(w.value),1)]),t("div",Te,[e[12]||(e[12]=t("span",null,"Código de Compra:",-1)),t("span",Se,d(w.value),1)]),t("div",De,[e[13]||(e[13]=t("span",null,"Fecha de Compra:",-1)),t("span",null,d(F.value),1)]),t("div",Ce,[e[14]||(e[14]=t("span",null,"Importe Pagado:",-1)),t("span",Ee,d(M.value)+" "+d(R.value),1)]),t("div",Pe,[e[15]||(e[15]=t("span",null,"Estado:",-1)),t("span",xe,d(U.value),1)])]),t("div",Ie,[e[22]||(e[22]=t("h3",null,"Próximos Pasos",-1)),t("ol",null,[t("li",null,[e[17]||(e[17]=C("Recuerda tu número de orden: ",-1)),t("strong",null,d(w.value),1)]),e[18]||(e[18]=t("li",null,"Recibirás un email de confirmación con tu comprobante",-1)),e[19]||(e[19]=t("li",null,"Puedes imprimir tu entrada desde el botón de abajo",-1)),e[20]||(e[20]=t("li",null,"Presenta tu entrada en la taquilla del cine",-1)),e[21]||(e[21]=t("li",null,"Llega 15 minutos antes de que comience la función",-1))])]),t("div",Re,[t("button",{onClick:H,class:"btn btn-primary btn-large",disabled:E.value||h.value||!n.value},[E.value?(N(),_("span",Le,"Imprimiendo...")):(N(),_("span",ze,"Imprimir Entrada"))],8,$e),t("button",{onClick:B,class:"btn btn-secondary btn-large",disabled:h.value||!n.value}," Descargar Entrada ",8,Oe),K(i,{to:"/",class:"btn btn-tertiary btn-large"},{default:Q(()=>[...e[23]||(e[23]=[C(" Volver al Inicio ",-1)])]),_:1})]),g.value?(N(),_("p",{key:4,class:X(["print-message",b.value])},d(g.value),3)):P("",!0),e[25]||(e[25]=t("div",{class:"contact-section"},[t("h4",null,"¿Necesitas ayuda?"),t("p",null,[C("Contacta con nosotros en "),t("strong",null,"info@cinea.es")]),t("p",null,[C("Teléfono: "),t("strong",null,"+34 91 123 4567")]),t("p",{class:"hours"},"Lun-Dom: 10:00 - 22:00")],-1))])]),e[26]||(e[26]=t("div",{id:"print-frame",style:{display:"none"}},null,-1))])}}},je=ee(Fe,[["__scopeId","data-v-b80481dc"]]);export{je as default};
