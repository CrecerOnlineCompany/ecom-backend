import{n as me,r as g,j as x,s as ve,b as pe,o as w,d as k,e as t,t as n,l as M,g as R,F as ge,m as be,f as fe,w as he,v as ye}from"./vendor-89c651cf.js";import{p as Ne}from"./paymentService-2ff17cdb.js";import{_ as _e,u as Ae}from"./index-75ea5159.js";const U={async printThermalTicket(r){try{const a=window.open("","","width=400,height=600");if(!a)return console.error("No se pudo abrir la ventana de impresión"),!1;const u=this.generateThermalHTML(r);return a.document.write(u),a.document.close(),a.onload=()=>{a.print(),setTimeout(()=>{a.close()},1e3)},!0}catch(a){return console.error("Error al imprimir:",a),!1}},async printMultipleTickets(r){try{const a=window.open("","","width=400,height=600");if(!a)return console.error("No se pudo abrir la ventana de impresión"),!1;let u=`
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="UTF-8">
          <style>
            ${this.getThermalStyles()}
          </style>
        </head>
        <body>
      `;return r.forEach((D,b)=>{u+=this.generateThermalTicketContent(D),b<r.length-1&&(u+='<div style="page-break-after: always; margin-top: 20px;"></div>')}),u+="</body></html>",a.document.write(u),a.document.close(),a.onload=()=>{a.print(),setTimeout(()=>{a.close()},1e3)},!0}catch(a){return console.error("Error al imprimir múltiples tickets:",a),!1}},generateThermalHTML(r){const a=this.generateThermalTicketContent(r);return`
      <!DOCTYPE html>
      <html>
      <head>
        <meta charset="UTF-8">
        <style>
          ${this.getThermalStyles()}
        </style>
      </head>
      <body>
        ${a}
      </body>
      </html>
    `},generateThermalTicketContent(r){const u=new Date().toLocaleDateString("es-ES",{day:"2-digit",month:"2-digit",year:"numeric",hour:"2-digit",minute:"2-digit"});return`
      <div class="thermal-ticket">
        <div class="thermal-header">
          <div class="cinema-name">CINEA</div>
          <div class="cinema-subtitle">Cines Independientes</div>
        </div>

        <div class="thermal-divider">═══════════════════════════</div>

        <div class="thermal-section">
          <div class="thermal-label">PELÍCULA:</div>
          <div class="thermal-value bold">${this.truncateText(r.movieTitle||"N/A",32)}</div>
        </div>

        <div class="thermal-section">
          <div class="thermal-row">
            <div class="thermal-col">
              <div class="thermal-label">FECHA:</div>
              <div class="thermal-value">${r.screeningDate||"N/A"}</div>
            </div>
            <div class="thermal-col">
              <div class="thermal-label">HORA:</div>
              <div class="thermal-value">${r.screeningTime||"N/A"}</div>
            </div>
          </div>
        </div>

        <div class="thermal-section">
          <div class="thermal-label">ASIENTO:</div>
          <div class="thermal-value bold">${r.seatNumber||"N/A"}</div>
        </div>

        <div class="thermal-section">
          <div class="thermal-label">ENTRADA:</div>
          <div class="thermal-barcode">${r.ticketNumber||"N/A"}</div>
          <div class="thermal-code-small">${r.ticketNumber||"N/A"}</div>
        </div>

        <div class="thermal-section">
          <div class="thermal-label">PRECIO:</div>
          <div class="thermal-value bold">${r.price||"0.00"} €</div>
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
    `},truncateText(r,a){return r.length<=a?r:r.substring(0,a-3)+"..."},getTicketData(r){const a=localStorage.getItem("tickets");if(a)try{const D=JSON.parse(a).find(b=>b.ticketNumber===r);if(D)return D}catch(u){console.error("Error al leer tickets del localStorage:",u)}return{ticketNumber:r,movieTitle:"Película",screeningDate:new Date().toLocaleDateString("es-ES"),screeningTime:"20:00",seatNumber:"N/A",price:"10.00"}}};const Se={class:"payment-success-page"},we={class:"container"},ke={class:"success-card"},Te={key:0,class:"loading-message"},xe={key:1,class:"loading-error"},Ce={key:2,class:"desk-assistance"},De={key:3,class:"ticket-display"},Ee={class:"ticket-section"},Pe={class:"info-grid order-grid"},Ie={class:"info-item"},$e={class:"info-value"},Le={class:"info-item"},Re={class:"info-value"},ze={class:"info-item"},Oe={class:"info-value"},Fe={class:"info-item"},Me={class:"info-value"},Ue={class:"info-item"},Be={class:"info-value"},je={class:"info-item"},qe={class:"info-value"},He={class:"info-item"},Ve={class:"info-value"},Je={class:"info-item"},We={class:"info-value"},Ye={class:"ticket-list-section"},Ge={class:"tickets-list"},Ke={class:"ticket-row"},Qe={class:"info-value code"},Xe={class:"ticket-row"},Ze={class:"info-value"},et={class:"ticket-row"},tt={class:"info-value"},st={class:"ticket-row"},at={class:"info-value"},nt={class:"purchase-details"},rt={class:"detail-row"},lt={class:"code"},ot={class:"detail-row"},it={class:"code"},dt={class:"detail-row"},ut={class:"detail-row"},ct={class:"amount"},mt={class:"detail-row"},vt={class:"status-badge status-success"},pt={class:"next-steps"},gt={class:"action-buttons"},bt=["disabled"],ft={key:0},ht={key:1},yt=["disabled"],Nt={__name:"PaymentSuccess",setup(r){const a=me(),u=Ae(),D=g(""),b=g(""),v=g(null),c=g([]),z=g(!1),B=g(!1),E=g(""),A=g(""),T=g("info"),d=g(null),C=g(null),S=g(!1),H=s=>{if(!s)return"N/A";const e=new Date(s);return Number.isNaN(e.getTime())?"N/A":e.toLocaleDateString("es-AR")},V=s=>{if(!s)return"N/A";const e=new Date(s);return Number.isNaN(e.getTime())?"N/A":e.toLocaleTimeString("es-AR",{hour:"2-digit",minute:"2-digit"})},G=s=>{if(typeof s=="boolean")return s;if(typeof s=="number")return s===1;if(typeof s=="string"){const e=s.trim().toLowerCase();return["1","true","yes","si","sí"].includes(e)}return!1},K=s=>G(s==null?void 0:s.non_number)?"S/N":(s==null?void 0:s.seat_code)||`F${(s==null?void 0:s.row_number)||""}-S${(s==null?void 0:s.seat_number)||""}`,Q=(s=[])=>{const e=s.map(o=>K(o)).filter(Boolean);if(!e.length)return"N/A";const l=[...new Set(e)];return l.length===1&&l[0]==="S/N"&&e.length>1?`S/N x${e.length}`:e.join(", ")},X=(s,e)=>{var p,f,h,y;const l=Array.isArray(s==null?void 0:s.details)?s.details:[],o=String((s==null?void 0:s.status)||"").toLowerCase(),i=o==="confirmed"?"Confirmado":o==="pending"?"Pendiente":o==="cancelled"?"Cancelado":"Pendiente";return{ticketNumber:String((s==null?void 0:s.ticket_number)||(s==null?void 0:s.id)||"N/A"),movieTitle:String(((f=(p=e==null?void 0:e.screening)==null?void 0:p.movie)==null?void 0:f.title)||"Película"),screeningDate:H((h=e==null?void 0:e.screening)==null?void 0:h.start_time),screeningTime:V((y=e==null?void 0:e.screening)==null?void 0:y.start_time),seatNumber:Q(l),price:String((s==null?void 0:s.price)||"0.00"),statusText:i}},Z=s=>{const e=String(s||"").toLowerCase();return e==="completed"||e==="approved"?"Completado":e==="processing"||e==="pending"?"Procesando":e==="rejected"||e==="declined"?"Rechazado":"Completado"},ee=x(()=>{var l,o;const s=((l=C.value)==null?void 0:l.paidAt)||((o=C.value)==null?void 0:o.updatedAt);return(s?new Date(s):new Date).toLocaleDateString("es-ES",{day:"2-digit",month:"2-digit",year:"numeric",hour:"2-digit",minute:"2-digit"})}),P=x(()=>{var s;return((s=d.value)==null?void 0:s.orderNumber)||b.value||"N/A"}),J=x(()=>{var e,l;const s=Number(((e=C.value)==null?void 0:e.amount)??((l=d.value)==null?void 0:l.totalAmount));return Number.isFinite(s)?s.toFixed(2):"No disponible"}),O=x(()=>c.value.length>0||!!v.value),te=x(()=>{const s=c.value.length||(v.value?1:0);return String(s)}),se=x(()=>c.value.length>1?"Imprimir Entradas":"Imprimir Entrada"),ae=x(()=>c.value.length>1?"Descargar Entradas":"Descargar Entrada"),F=x(()=>{var s,e;return((s=C.value)==null?void 0:s.currency)||((e=d.value)==null?void 0:e.currency)||"ARS"}),ne=x(()=>{var s;return Z((s=C.value)==null?void 0:s.status)}),W=s=>String(s||"").trim(),re=s=>{var o,i;const e=W((o=u.currentSession)==null?void 0:o.order_number),l=W(s||b.value||((i=d.value)==null?void 0:i.orderNumber));!e||!l||e===l&&(u.clearCart(),u.clearPaymentSession())};ve(async()=>{var s;if(D.value=a.query.ticket||a.params.ticket||"N/A",b.value=a.query.order||a.params.order||"",d.value={orderNumber:String(b.value||"N/A"),currency:"ARS",movieTitle:"",cinemaName:"",roomName:"",format:"",screeningDate:"",screeningTime:""},!b.value){S.value=!0,E.value="Falta el número de orden en la URL. Continúa en ventanilla.";return}await ie(),re((s=d.value)==null?void 0:s.orderNumber),window.scrollTo(0,0)});const le=(s=[])=>{if(s.length)try{const e=localStorage.getItem("tickets"),l=e?JSON.parse(e):[],o=new Map(l.map(i=>[i.ticketNumber,i]));for(const i of s)i!=null&&i.ticketNumber&&o.set(i.ticketNumber,i);localStorage.setItem("tickets",JSON.stringify(Array.from(o.values())))}catch(e){console.warn("No se pudo persistir tickets en localStorage:",e)}},oe=async()=>{if(!c.value.length&&!v.value)return;const s=c.value.length?c.value:[v.value];let e=!1;s.length>1?e=await U.printMultipleTickets(s):e=await U.printThermalTicket(s[0]),e?(T.value="success",A.value="Impresión automática iniciada."):(T.value="error",A.value='No se pudo imprimir automáticamente. Usa el botón "Imprimir Entrada".')},ie=async()=>{var s,e,l,o,i,p,f,h,y,N,$;B.value=!0,E.value="",S.value=!1;try{const _=await Ne.getPaymentOrderDetails(b.value)||{};if(!_.success||!_.order)throw new Error("Respuesta inválida del endpoint de detalle de orden");const m=_.order,Y=Array.isArray(_.tickets)?_.tickets:[],I=(Array.isArray(_.payments)?_.payments:[])[0]||{},j=Number(m.total_amount??((s=I==null?void 0:I.response_data)==null?void 0:s.amount));if(!Number.isFinite(j))throw new Error("Monto no disponible desde backend");if(d.value={orderNumber:String(m.order_number||b.value),currency:String(m.currency||"ARS"),totalAmount:j,movieTitle:String(((l=(e=m.screening)==null?void 0:e.movie)==null?void 0:l.title)||"N/A"),cinemaName:String(((p=(i=(o=m.screening)==null?void 0:o.room)==null?void 0:i.cinema)==null?void 0:p.name)||"N/A"),roomName:String(((h=(f=m.screening)==null?void 0:f.room)==null?void 0:h.name)||"N/A"),format:String(((y=m.screening)==null?void 0:y.format)||"N/A"),screeningDate:H((N=m.screening)==null?void 0:N.start_time),screeningTime:V(($=m.screening)==null?void 0:$.start_time)},C.value={amount:j,status:I.status||m.status||"completed",paidAt:m.paid_at||I.completed_at||null,updatedAt:I.updated_at||m.updated_at||null,currency:String(m.currency||"ARS")},Y.length===0){S.value=!0,E.value="Pago confirmado, pero las entradas aún no están disponibles. Continúa en ventanilla con tu número de orden.",v.value=null,c.value=[],T.value="error",A.value="Impresión deshabilitada: ve a ventanilla con tu número de orden.";return}const q=Y.map(ce=>X(ce,m));c.value=q,v.value=q[0],le(q),await oe()}catch(L){console.error("Error al cargar datos del ticket:",L),E.value="Faltan datos del pago o el backend no respondió. Continúa en ventanilla.",S.value=!0,v.value=null,c.value=[],C.value=null,T.value="error",A.value="Impresión deshabilitada: ve a ventanilla con tu número de orden."}finally{B.value=!1}},de=async()=>{if(S.value){T.value="error",A.value="Impresión deshabilitada: ve a ventanilla con tu número de orden.";return}z.value=!0;try{const s=c.value.length?c.value:[v.value];(s.length>1?await U.printMultipleTickets(s):await U.printThermalTicket(s[0]))?(T.value="success",A.value="Impresión iniciada correctamente."):(T.value="error",A.value="No se pudo completar la impresión. Verifica tu impresora.")}catch(s){console.error("Error durante la impresión:",s),T.value="error",A.value="Error al intentar imprimir. Intenta de nuevo."}finally{z.value=!1}},ue=()=>{var f,h,y,N,$,L;if(!O.value||S.value)return;const e=(c.value.length?c.value:[v.value]).map(_=>`
      <tr>
        <td style="padding: 8px; border: 1px solid #ddd;">${_.ticketNumber}</td>
        <td style="padding: 8px; border: 1px solid #ddd;">${_.seatNumber}</td>
        <td style="padding: 8px; border: 1px solid #ddd;">${_.price} ${F.value}</td>
      </tr>
    `).join(""),l=`
    <div style="text-align: center; padding: 20px; font-family: Arial, sans-serif;">
      <h1>CINEA - Entradas</h1>
      <p><strong>${((f=d.value)==null?void 0:f.movieTitle)||((h=v.value)==null?void 0:h.movieTitle)||"Película"}</strong></p>
      <p>Número de orden: ${P.value}</p>
      <p>Fecha: ${((y=d.value)==null?void 0:y.screeningDate)||((N=v.value)==null?void 0:N.screeningDate)||"N/A"}</p>
      <p>Hora: ${(($=d.value)==null?void 0:$.screeningTime)||((L=v.value)==null?void 0:L.screeningTime)||"N/A"}</p>
      <table style="margin: 16px auto; border-collapse: collapse; min-width: 420px;">
        <thead>
          <tr>
            <th style="padding: 8px; border: 1px solid #ddd;">Ticket</th>
            <th style="padding: 8px; border: 1px solid #ddd;">Asiento(s)</th>
            <th style="padding: 8px; border: 1px solid #ddd;">Precio</th>
          </tr>
        </thead>
        <tbody>
          ${e}
        </tbody>
      </table>
    </div>
  `,o=new Blob([l],{type:"text/html"}),i=window.URL.createObjectURL(o),p=document.createElement("a");p.href=i,p.download=`entrada_${P.value}.html`,p.click(),window.URL.revokeObjectURL(i)};return(s,e)=>{var o,i,p,f,h,y;const l=pe("router-link");return w(),k("div",Se,[t("div",we,[t("div",ke,[e[31]||(e[31]=t("div",{class:"success-header"},[t("div",{class:"success-icon-large icon-success"}),t("h1",null,"¡Pago Exitoso!"),t("p",{class:"success-subtitle"},"Tu compra ha sido procesada correctamente")],-1)),B.value?(w(),k("p",Te,"Cargando información de la compra...")):E.value?(w(),k("p",xe,n(E.value),1)):M("",!0),S.value?(w(),k("div",Ce,[e[1]||(e[1]=t("h3",null,"Atención en ventanilla requerida",-1)),e[2]||(e[2]=t("p",null,"No pudimos validar todos los datos del pago en línea.",-1)),t("p",null,[e[0]||(e[0]=t("strong",null,"Orden:",-1)),R(" "+n(P.value),1)]),e[3]||(e[3]=t("p",null,"Acércate a ventanilla con este número para finalizar la entrega de entradas.",-1))])):M("",!0),O.value&&!S.value?(w(),k("div",De,[t("div",Ee,[e[12]||(e[12]=t("h3",null,"Datos de la Orden",-1)),t("div",Pe,[t("div",Ie,[e[4]||(e[4]=t("span",{class:"info-label"},"Película:",-1)),t("span",$e,n(((o=d.value)==null?void 0:o.movieTitle)||"N/A"),1)]),t("div",Le,[e[5]||(e[5]=t("span",{class:"info-label"},"Cine:",-1)),t("span",Re,n(((i=d.value)==null?void 0:i.cinemaName)||"N/A"),1)]),t("div",ze,[e[6]||(e[6]=t("span",{class:"info-label"},"Sala:",-1)),t("span",Oe,n(((p=d.value)==null?void 0:p.roomName)||"N/A"),1)]),t("div",Fe,[e[7]||(e[7]=t("span",{class:"info-label"},"Formato:",-1)),t("span",Me,n(((f=d.value)==null?void 0:f.format)||"N/A"),1)]),t("div",Ue,[e[8]||(e[8]=t("span",{class:"info-label"},"Fecha:",-1)),t("span",Be,n(((h=d.value)==null?void 0:h.screeningDate)||"N/A"),1)]),t("div",je,[e[9]||(e[9]=t("span",{class:"info-label"},"Hora:",-1)),t("span",qe,n(((y=d.value)==null?void 0:y.screeningTime)||"N/A"),1)]),t("div",He,[e[10]||(e[10]=t("span",{class:"info-label"},"Entradas:",-1)),t("span",Ve,n(te.value),1)]),t("div",Je,[e[11]||(e[11]=t("span",{class:"info-label"},"Total:",-1)),t("span",We,n(J.value)+" "+n(F.value),1)])])]),t("div",Ye,[e[17]||(e[17]=t("h3",null,"Entradas",-1)),t("div",Ge,[(w(!0),k(ge,null,be(c.value,N=>(w(),k("div",{key:N.ticketNumber,class:"ticket-item"},[t("div",Ke,[e[13]||(e[13]=t("span",{class:"info-label"},"Número:",-1)),t("span",Qe,n(N.ticketNumber),1)]),t("div",Xe,[e[14]||(e[14]=t("span",{class:"info-label"},"Asiento(s):",-1)),t("span",Ze,n(N.seatNumber),1)]),t("div",et,[e[15]||(e[15]=t("span",{class:"info-label"},"Precio:",-1)),t("span",tt,n(N.price)+" "+n(F.value),1)]),t("div",st,[e[16]||(e[16]=t("span",{class:"info-label"},"Estado:",-1)),t("span",at,n(N.statusText),1)])]))),128))])])])):M("",!0),t("div",nt,[e[23]||(e[23]=t("h3",null,"Detalles de la Compra",-1)),t("div",rt,[e[18]||(e[18]=t("span",null,"Número de Orden:",-1)),t("span",lt,n(P.value),1)]),t("div",ot,[e[19]||(e[19]=t("span",null,"Código de Compra:",-1)),t("span",it,n(P.value),1)]),t("div",dt,[e[20]||(e[20]=t("span",null,"Fecha de Compra:",-1)),t("span",null,n(ee.value),1)]),t("div",ut,[e[21]||(e[21]=t("span",null,"Importe Pagado:",-1)),t("span",ct,n(J.value)+" "+n(F.value),1)]),t("div",mt,[e[22]||(e[22]=t("span",null,"Estado:",-1)),t("span",vt,n(ne.value),1)])]),t("div",pt,[e[29]||(e[29]=t("h3",null,"Próximos Pasos",-1)),t("ol",null,[t("li",null,[e[24]||(e[24]=R("Recuerda tu número de orden: ",-1)),t("strong",null,n(P.value),1)]),e[25]||(e[25]=t("li",null,"Recibirás un email de confirmación con tu comprobante",-1)),e[26]||(e[26]=t("li",null,"Puedes imprimir tu entrada desde el botón de abajo",-1)),e[27]||(e[27]=t("li",null,"Presenta tu entrada en la taquilla del cine",-1)),e[28]||(e[28]=t("li",null,"Llega 15 minutos antes de que comience la función",-1))])]),t("div",gt,[t("button",{onClick:de,class:"btn btn-primary btn-large",disabled:z.value||S.value||!O.value},[z.value?(w(),k("span",ht,"Imprimiendo...")):(w(),k("span",ft,n(se.value),1))],8,bt),t("button",{onClick:ue,class:"btn btn-secondary btn-large",disabled:S.value||!O.value},n(ae.value),9,yt),fe(l,{to:"/",class:"btn btn-tertiary btn-large"},{default:he(()=>[...e[30]||(e[30]=[R(" Volver al Inicio ",-1)])]),_:1})]),A.value?(w(),k("p",{key:4,class:ye(["print-message",T.value])},n(A.value),3)):M("",!0),e[32]||(e[32]=t("div",{class:"contact-section"},[t("h4",null,"¿Necesitas ayuda?"),t("p",null,[R("Contacta con nosotros en "),t("strong",null,"info@cinea.es")]),t("p",null,[R("Teléfono: "),t("strong",null,"+34 91 123 4567")]),t("p",{class:"hours"},"Lun-Dom: 10:00 - 22:00")],-1))])]),e[33]||(e[33]=t("div",{id:"print-frame",style:{display:"none"}},null,-1))])}}},kt=_e(Nt,[["__scopeId","data-v-e64833ee"]]);export{kt as default};
