import{C as J,r as u,p as S,j as W,b as Y,o as N,d as _,e as t,t as o,k as P,g as D,f as G,w as K,n as Q}from"./vendor-d71df2f6.js";import{p as X}from"./paymentService-2bb0719c.js";import{_ as Z}from"./index-33170dbd.js";const x={async printThermalTicket(l){try{const s=window.open("","","width=400,height=600");if(!s)return console.error("No se pudo abrir la ventana de impresión"),!1;const c=this.generateThermalHTML(l);return s.document.write(c),s.document.close(),s.onload=()=>{s.print(),setTimeout(()=>{s.close()},1e3)},!0}catch(s){return console.error("Error al imprimir:",s),!1}},async printMultipleTickets(l){try{const s=window.open("","","width=400,height=600");if(!s)return console.error("No se pudo abrir la ventana de impresión"),!1;let c=`
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="UTF-8">
          <style>
            ${this.getThermalStyles()}
          </style>
        </head>
        <body>
      `;return l.forEach((m,n)=>{c+=this.generateThermalTicketContent(m),n<l.length-1&&(c+='<div style="page-break-after: always; margin-top: 20px;"></div>')}),c+="</body></html>",s.document.write(c),s.document.close(),s.onload=()=>{s.print(),setTimeout(()=>{s.close()},1e3)},!0}catch(s){return console.error("Error al imprimir múltiples tickets:",s),!1}},generateThermalHTML(l){const s=this.generateThermalTicketContent(l);return`
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
    `},generateThermalTicketContent(l){const c=new Date().toLocaleDateString("es-ES",{day:"2-digit",month:"2-digit",year:"numeric",hour:"2-digit",minute:"2-digit"});return`
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
          <div class="thermal-small">Impreso: ${c}</div>
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
    `},truncateText(l,s){return l.length<=s?l:l.substring(0,s-3)+"..."},getTicketData(l){const s=localStorage.getItem("tickets");if(s)try{const m=JSON.parse(s).find(n=>n.ticketNumber===l);if(m)return m}catch(c){console.error("Error al leer tickets del localStorage:",c)}return{ticketNumber:l,movieTitle:"Película",screeningDate:new Date().toLocaleDateString("es-ES"),screeningTime:"20:00",seatNumber:"N/A",price:"10.00"}}};const ee={class:"payment-success-page"},te={class:"container"},ae={class:"success-card"},se={key:0,class:"loading-message"},ne={key:1,class:"loading-error"},le={key:2,class:"desk-assistance"},ie={key:3,class:"ticket-display"},re={class:"ticket-section"},oe={class:"info-grid"},de={class:"info-item"},ce={class:"info-value code"},ue={class:"info-item"},me={class:"info-value"},ve={class:"info-item"},pe={class:"info-value"},ge={class:"info-item"},he={class:"info-value"},be={class:"info-item"},fe={class:"info-value"},ye={class:"info-item"},Ne={class:"info-value"},_e={class:"purchase-details"},ke={class:"detail-row"},we={class:"code"},Ae={class:"detail-row"},Te={class:"code"},Se={class:"detail-row"},De={class:"detail-row"},Ee={class:"amount"},Ce={class:"detail-row"},Pe={class:"status-badge status-success"},xe={class:"next-steps"},Ie={class:"action-buttons"},Re=["disabled"],$e={key:0},ze={key:1},Le=["disabled"],Oe={__name:"PaymentSuccess",setup(l){const s=J(),c=u(""),m=u(""),n=u(null),k=u([]),E=u(!1),I=u(!1),T=u(""),p=u(""),f=u("info"),C=u(null),w=u(null),g=u(!1),$=a=>{if(!a)return"N/A";const e=new Date(a);return Number.isNaN(e.getTime())?"N/A":e.toLocaleDateString("es-AR")},z=a=>{if(!a)return"N/A";const e=new Date(a);return Number.isNaN(e.getTime())?"N/A":e.toLocaleTimeString("es-AR",{hour:"2-digit",minute:"2-digit"})},L=(a,e)=>{var r,y,h,b;const d=(Array.isArray(a==null?void 0:a.details)?a.details:[]).map(v=>(v==null?void 0:v.seat_code)||`${(v==null?void 0:v.row_number)||""}${(v==null?void 0:v.seat_number)||""}`).filter(Boolean);return{ticketNumber:String((a==null?void 0:a.ticket_number)||(a==null?void 0:a.id)||"N/A"),movieTitle:String(((y=(r=e==null?void 0:e.screening)==null?void 0:r.movie)==null?void 0:y.title)||"Película"),screeningDate:$((h=e==null?void 0:e.screening)==null?void 0:h.start_time),screeningTime:z((b=e==null?void 0:e.screening)==null?void 0:b.start_time),seatNumber:d.length?d.join(", "):"N/A",price:String((a==null?void 0:a.price)||"0.00")}},O=a=>{const e=String(a||"").toLowerCase();return e==="completed"||e==="approved"?"Completado":e==="processing"||e==="pending"?"Procesando":e==="rejected"||e==="declined"?"Rechazado":"Completado"},F=S(()=>{var i,d;const a=((i=w.value)==null?void 0:i.paidAt)||((d=w.value)==null?void 0:d.updatedAt);return(a?new Date(a):new Date).toLocaleDateString("es-ES",{day:"2-digit",month:"2-digit",year:"numeric",hour:"2-digit",minute:"2-digit"})}),A=S(()=>{var a;return((a=C.value)==null?void 0:a.orderNumber)||m.value||"N/A"}),M=S(()=>{var e;const a=Number((e=w.value)==null?void 0:e.amount);return Number.isFinite(a)?a.toFixed(2):"No disponible"}),R=S(()=>{var a,e;return((a=w.value)==null?void 0:a.currency)||((e=C.value)==null?void 0:e.currency)||"ARS"}),U=S(()=>{var a;return O((a=w.value)==null?void 0:a.status)});W(async()=>{if(c.value=s.query.ticket||s.params.ticket||"N/A",m.value=s.query.order||s.params.order||"",C.value={orderNumber:String(m.value||"N/A"),currency:"ARS"},!m.value){g.value=!0,T.value="Falta el número de orden en la URL. Continúa en ventanilla.";return}await q(),window.scrollTo(0,0)});const V=(a=[])=>{if(a.length)try{const e=localStorage.getItem("tickets"),i=e?JSON.parse(e):[],d=new Map(i.map(r=>[r.ticketNumber,r]));for(const r of a)r!=null&&r.ticketNumber&&d.set(r.ticketNumber,r);localStorage.setItem("tickets",JSON.stringify(Array.from(d.values())))}catch(e){console.warn("No se pudo persistir tickets en localStorage:",e)}},j=async()=>{if(!k.value.length&&!n.value)return;const a=k.value.length?k.value:[n.value];let e=!1;a.length>1?e=await x.printMultipleTickets(a):e=await x.printThermalTicket(a[0]),e?(f.value="success",p.value="Impresión automática iniciada."):(f.value="error",p.value='No se pudo imprimir automáticamente. Usa el botón "Imprimir Entrada".')},q=async()=>{I.value=!0,T.value="",g.value=!1;try{const e=await X.getPaymentOrderDetails(m.value)||{};if(!e.success||!e.order)throw new Error("Respuesta inválida del endpoint de detalle de orden");const i=e.order,d=Array.isArray(e.tickets)?e.tickets:[],r=Array.isArray(e.payments)?e.payments:[];if(d.length===0)throw new Error("Datos de tickets incompletos");if(r.length===0)throw new Error("Datos de pago incompletos");const y=Number(i.total_amount);if(!Number.isFinite(y))throw new Error("Monto no disponible desde backend");const h=d.map(v=>L(v,i));k.value=h,n.value=h[0],C.value={orderNumber:String(i.order_number||m.value),currency:String(i.currency||"ARS")};const b=r[0];w.value={amount:y,status:b.status||i.status||"completed",paidAt:i.paid_at||b.completed_at||null,updatedAt:b.updated_at||i.updated_at||null,currency:String(i.currency||"ARS")},V(h),await j()}catch(a){console.error("Error al cargar datos del ticket:",a),T.value="Faltan datos del pago o el backend no respondió. Continúa en ventanilla.",g.value=!0,n.value=null,k.value=[],w.value=null,f.value="error",p.value="Impresión deshabilitada: ve a ventanilla con tu número de orden."}finally{I.value=!1}},H=async()=>{if(g.value){f.value="error",p.value="Impresión deshabilitada: ve a ventanilla con tu número de orden.";return}E.value=!0;try{const a=k.value.length?k.value:[n.value];(a.length>1?await x.printMultipleTickets(a):await x.printThermalTicket(a[0]))?(f.value="success",p.value="Impresión iniciada correctamente."):(f.value="error",p.value="No se pudo completar la impresión. Verifica tu impresora.")}catch(a){console.error("Error durante la impresión:",a),f.value="error",p.value="Error al intentar imprimir. Intenta de nuevo."}finally{E.value=!1}},B=()=>{var r,y,h,b;if(!n.value||g.value)return;const a=`
    <div style="text-align: center; padding: 20px; font-family: Arial, sans-serif;">
      <h1>CINEA - Entrada</h1>
      <p><strong>${((r=n.value)==null?void 0:r.movieTitle)||"Película"}</strong></p>
      <p>Número de orden: ${A.value}</p>
      <p>Fecha: ${((y=n.value)==null?void 0:y.screeningDate)||"N/A"}</p>
      <p>Hora: ${((h=n.value)==null?void 0:h.screeningTime)||"N/A"}</p>
      <p>Asiento: ${((b=n.value)==null?void 0:b.seatNumber)||"N/A"}</p>
    </div>
  `,e=new Blob([a],{type:"text/html"}),i=window.URL.createObjectURL(e),d=document.createElement("a");d.href=i,d.download=`entrada_${A.value}.html`,d.click(),window.URL.revokeObjectURL(i)};return(a,e)=>{const i=Y("router-link");return N(),_("div",ee,[t("div",te,[t("div",ae,[e[24]||(e[24]=t("div",{class:"success-header"},[t("div",{class:"success-icon-large icon-success"}),t("h1",null,"¡Pago Exitoso!"),t("p",{class:"success-subtitle"},"Tu compra ha sido procesada correctamente")],-1)),I.value?(N(),_("p",se,"Cargando información de la compra...")):T.value?(N(),_("p",ne,o(T.value),1)):P("",!0),g.value?(N(),_("div",le,[e[1]||(e[1]=t("h3",null,"Atención en ventanilla requerida",-1)),e[2]||(e[2]=t("p",null,"No pudimos validar todos los datos del pago en línea.",-1)),t("p",null,[e[0]||(e[0]=t("strong",null,"Orden:",-1)),D(" "+o(A.value),1)]),e[3]||(e[3]=t("p",null,"Acércate a ventanilla con este número para finalizar la entrega de entradas.",-1))])):P("",!0),n.value&&!g.value?(N(),_("div",ie,[t("div",re,[e[10]||(e[10]=t("h3",null,"Información de la Entrada",-1)),t("div",oe,[t("div",de,[e[4]||(e[4]=t("span",{class:"info-label"},"Número de Entrada:",-1)),t("span",ce,o(n.value.ticketNumber),1)]),t("div",ue,[e[5]||(e[5]=t("span",{class:"info-label"},"Película:",-1)),t("span",me,o(n.value.movieTitle),1)]),t("div",ve,[e[6]||(e[6]=t("span",{class:"info-label"},"Fecha:",-1)),t("span",pe,o(n.value.screeningDate),1)]),t("div",ge,[e[7]||(e[7]=t("span",{class:"info-label"},"Hora:",-1)),t("span",he,o(n.value.screeningTime),1)]),t("div",be,[e[8]||(e[8]=t("span",{class:"info-label"},"Asiento:",-1)),t("span",fe,o(n.value.seatNumber),1)]),t("div",ye,[e[9]||(e[9]=t("span",{class:"info-label"},"Precio:",-1)),t("span",Ne,o(n.value.price)+" "+o(R.value),1)])])])])):P("",!0),t("div",_e,[e[16]||(e[16]=t("h3",null,"Detalles de la Compra",-1)),t("div",ke,[e[11]||(e[11]=t("span",null,"Número de Orden:",-1)),t("span",we,o(A.value),1)]),t("div",Ae,[e[12]||(e[12]=t("span",null,"Código de Compra:",-1)),t("span",Te,o(A.value),1)]),t("div",Se,[e[13]||(e[13]=t("span",null,"Fecha de Compra:",-1)),t("span",null,o(F.value),1)]),t("div",De,[e[14]||(e[14]=t("span",null,"Importe Pagado:",-1)),t("span",Ee,o(M.value)+" "+o(R.value),1)]),t("div",Ce,[e[15]||(e[15]=t("span",null,"Estado:",-1)),t("span",Pe,o(U.value),1)])]),t("div",xe,[e[22]||(e[22]=t("h3",null,"Próximos Pasos",-1)),t("ol",null,[t("li",null,[e[17]||(e[17]=D("Recuerda tu número de orden: ",-1)),t("strong",null,o(A.value),1)]),e[18]||(e[18]=t("li",null,"Recibirás un email de confirmación con tu comprobante",-1)),e[19]||(e[19]=t("li",null,"Puedes imprimir tu entrada desde el botón de abajo",-1)),e[20]||(e[20]=t("li",null,"Presenta tu entrada en la taquilla del cine",-1)),e[21]||(e[21]=t("li",null,"Llega 15 minutos antes de que comience la función",-1))])]),t("div",Ie,[t("button",{onClick:H,class:"btn btn-primary btn-large",disabled:E.value||g.value||!n.value},[E.value?(N(),_("span",ze,"Imprimiendo...")):(N(),_("span",$e,"Imprimir Entrada"))],8,Re),t("button",{onClick:B,class:"btn btn-secondary btn-large",disabled:g.value||!n.value}," Descargar Entrada ",8,Le),G(i,{to:"/",class:"btn btn-tertiary btn-large"},{default:K(()=>[...e[23]||(e[23]=[D(" Volver al Inicio ",-1)])]),_:1})]),p.value?(N(),_("p",{key:4,class:Q(["print-message",f.value])},o(p.value),3)):P("",!0),e[25]||(e[25]=t("div",{class:"contact-section"},[t("h4",null,"¿Necesitas ayuda?"),t("p",null,[D("Contacta con nosotros en "),t("strong",null,"info@cinea.es")]),t("p",null,[D("Teléfono: "),t("strong",null,"+34 91 123 4567")]),t("p",{class:"hours"},"Lun-Dom: 10:00 - 22:00")],-1))])]),e[26]||(e[26]=t("div",{id:"print-frame",style:{display:"none"}},null,-1))])}}},Ve=Z(Oe,[["__scopeId","data-v-a2d4e1cf"]]);export{Ve as default};
