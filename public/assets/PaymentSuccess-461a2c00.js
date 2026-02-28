import{s as H,C as j,r as p,p as w,j as B,b as J,o as b,d as f,e as t,t as m,k as C,g as A,f as W,w as Y,n as G}from"./vendor-d71df2f6.js";import{p as K}from"./paymentService-03f14570.js";import{_ as Q}from"./index-e4f8aca7.js";const S={async printThermalTicket(l){try{const s=window.open("","","width=400,height=600");if(!s)return console.error("No se pudo abrir la ventana de impresión"),!1;const c=this.generateThermalHTML(l);return s.document.write(c),s.document.close(),s.onload=()=>{s.print(),setTimeout(()=>{s.close()},1e3)},!0}catch(s){return console.error("Error al imprimir:",s),!1}},async printMultipleTickets(l){try{const s=window.open("","","width=400,height=600");if(!s)return console.error("No se pudo abrir la ventana de impresión"),!1;let c=`
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="UTF-8">
          <style>
            ${this.getThermalStyles()}
          </style>
        </head>
        <body>
      `;return l.forEach((d,n)=>{c+=this.generateThermalTicketContent(d),n<l.length-1&&(c+='<div style="page-break-after: always; margin-top: 20px;"></div>')}),c+="</body></html>",s.document.write(c),s.document.close(),s.onload=()=>{s.print(),setTimeout(()=>{s.close()},1e3)},!0}catch(s){return console.error("Error al imprimir múltiples tickets:",s),!1}},generateThermalHTML(l){const s=this.generateThermalTicketContent(l);return`
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
    `},truncateText(l,s){return l.length<=s?l:l.substring(0,s-3)+"..."},getTicketData(l){const s=localStorage.getItem("tickets");if(s)try{const d=JSON.parse(s).find(n=>n.ticketNumber===l);if(d)return d}catch(c){console.error("Error al leer tickets del localStorage:",c)}return{ticketNumber:l,movieTitle:"Película",screeningDate:new Date().toLocaleDateString("es-ES"),screeningTime:"20:00",seatNumber:"N/A",price:"10.00"}}};const X={class:"payment-success-page"},Z={class:"container"},ee={class:"success-card"},te={key:0,class:"loading-message"},ae={key:1,class:"loading-error"},se={key:2,class:"ticket-display"},ie={class:"ticket-section"},ne={class:"info-grid"},le={class:"info-item"},re={class:"info-value code"},oe={class:"info-item"},de={class:"info-value"},ce={class:"info-item"},ue={class:"info-value"},me={class:"info-item"},pe={class:"info-value"},ve={class:"info-item"},ge={class:"info-value"},he={class:"info-item"},be={class:"info-value"},fe={class:"purchase-details"},ye={class:"detail-row"},_e={class:"code"},ke={class:"detail-row"},Ne={class:"code"},Te={class:"detail-row"},we={class:"detail-row"},Ae={class:"amount"},Se={class:"detail-row"},De={class:"status-badge status-success"},Ce={class:"next-steps"},Ee={class:"action-buttons"},Pe=["disabled"],xe={key:0},Ie={key:1},$e={__name:"PaymentSuccess",setup(l){const s=H(),c=j(),d=p(""),n=p(null),v=p([]),k=p(!1),D=p(!1),N=p(""),g=p(""),y=p("info"),T=p(null),_=p(null),I=(a,e)=>{if(!(!a||!e))return e.split(".").reduce((i,r)=>i==null?void 0:i[r],a)},u=(a,e=[])=>{for(const i of e){const r=I(a,i);if(r!=null&&r!=="")return r}return null},E=(a,e="")=>{const i=u(a,["seat_row","seatRow"])||"",r=u(a,["seat_number","seatNumber"])||"",h=u(a,["seat_code","seatCode"])||`${i}${r}`||"N/A";return{ticketNumber:String(u(a,["ticket_number","ticketNumber"])||e||"N/A"),movieTitle:String(u(a,["movie_title","movieTitle"])||"Película"),screeningDate:String(u(a,["screening_date","screeningDate"])||"N/A"),screeningTime:String(u(a,["screening_time","screeningTime"])||"N/A"),seatNumber:h,price:String(u(a,["price","total_price","amount"])||"0.00")}},$=a=>{const e=String(a||"").toLowerCase();return e==="completed"||e==="approved"?"Completado":e==="processing"||e==="pending"?"Procesando":e==="rejected"||e==="declined"?"Rechazado":"Completado"},z=w(()=>{var i,r;const a=((i=_.value)==null?void 0:i.paidAt)||((r=_.value)==null?void 0:r.updatedAt);return(a?new Date(a):new Date).toLocaleDateString("es-ES",{day:"2-digit",month:"2-digit",year:"numeric",hour:"2-digit",minute:"2-digit"})}),L=w(()=>{var a;return((a=T.value)==null?void 0:a.orderNumber)||"N/A"}),R=w(()=>{var e,i;return Number(((e=_.value)==null?void 0:e.amount)||((i=n.value)==null?void 0:i.price)||0).toFixed(2)}),O=w(()=>{var a;return $((a=_.value)==null?void 0:a.status)});B(async()=>{if(d.value=c.query.ticket||c.params.ticket||"N/A",!d.value||d.value==="N/A"){s.push("/");return}await U(),window.scrollTo(0,0)});const M=(a=[])=>{if(a.length)try{const e=localStorage.getItem("tickets"),i=e?JSON.parse(e):[],r=new Map(i.map(o=>[o.ticketNumber,o]));for(const o of a)o!=null&&o.ticketNumber&&r.set(o.ticketNumber,o);localStorage.setItem("tickets",JSON.stringify(Array.from(r.values())))}catch(e){console.warn("No se pudo persistir tickets en localStorage:",e)}},F=async()=>{if(!v.value.length&&!n.value)return;const a=v.value.length?v.value:[n.value];let e=!1;a.length>1?e=await S.printMultipleTickets(a):e=await S.printThermalTicket(a[0]),e?(y.value="success",g.value="Impresión automática iniciada."):(y.value="error",g.value='No se pudo imprimir automáticamente. Usa el botón "Imprimir Entrada".')},U=async()=>{var a;D.value=!0,N.value="";try{const i=await K.getPaymentStatus(d.value)||{},r=u(i,["tickets","data.tickets","order.tickets","payment.tickets"]);let o=[];Array.isArray(r)&&r.length>0&&(o=r.map(h=>E(h,d.value))),o.length||(o=[E(i,d.value)]),v.value=o,n.value=o[0],T.value={orderNumber:String(u(i,["order_number","order.number","data.order_number"])||c.query.order||d.value)},_.value={amount:Number(u(i,["total_price","amount","payment.amount","data.total_price","data.amount"])||((a=n.value)==null?void 0:a.price)||0),status:u(i,["status","payment.status","data.status"])||"completed",paidAt:u(i,["paid_at","payment.paid_at","data.paid_at"])||null,updatedAt:u(i,["updated_at","payment.updated_at","data.updated_at"])||null},M(o),await F()}catch(e){console.error("Error al cargar datos del ticket:",e),N.value="No se pudieron cargar todos los datos de la compra. Puedes imprimir manualmente.",n.value={ticketNumber:d.value,movieTitle:"Película Seleccionada",screeningDate:new Date().toLocaleDateString("es-ES"),screeningTime:"20:00",seatNumber:"A1",price:"10.00"},v.value=[n.value],T.value={orderNumber:d.value},c.query.order&&(T.value.orderNumber=String(c.query.order)),_.value={amount:Number(n.value.price),status:"completed",paidAt:null,updatedAt:null}}finally{D.value=!1}},V=async()=>{k.value=!0;try{const a=v.value.length?v.value:[n.value];(a.length>1?await S.printMultipleTickets(a):await S.printThermalTicket(a[0]))?(y.value="success",g.value="Impresión iniciada correctamente."):(y.value="error",g.value="No se pudo completar la impresión. Verifica tu impresora.")}catch(a){console.error("Error durante la impresión:",a),y.value="error",g.value="Error al intentar imprimir. Intenta de nuevo."}finally{k.value=!1}},q=()=>{var o,h,P,x;const a=`
    <div style="text-align: center; padding: 20px; font-family: Arial, sans-serif;">
      <h1>CINEA - Entrada</h1>
      <p><strong>${((o=n.value)==null?void 0:o.movieTitle)||"Película"}</strong></p>
      <p>Número: ${d.value}</p>
      <p>Fecha: ${((h=n.value)==null?void 0:h.screeningDate)||"N/A"}</p>
      <p>Hora: ${((P=n.value)==null?void 0:P.screeningTime)||"N/A"}</p>
      <p>Asiento: ${((x=n.value)==null?void 0:x.seatNumber)||"N/A"}</p>
    </div>
  `,e=new Blob([a],{type:"text/html"}),i=window.URL.createObjectURL(e),r=document.createElement("a");r.href=i,r.download=`entrada_${d.value}.html`,r.click(),window.URL.revokeObjectURL(i)};return(a,e)=>{const i=J("router-link");return b(),f("div",X,[t("div",Z,[t("div",ee,[e[20]||(e[20]=t("div",{class:"success-header"},[t("div",{class:"success-icon-large icon-success"}),t("h1",null,"¡Pago Exitoso!"),t("p",{class:"success-subtitle"},"Tu compra ha sido procesada correctamente")],-1)),D.value?(b(),f("p",te,"Cargando información de la compra...")):N.value?(b(),f("p",ae,m(N.value),1)):C("",!0),n.value?(b(),f("div",se,[t("div",ie,[e[6]||(e[6]=t("h3",null,"Información de la Entrada",-1)),t("div",ne,[t("div",le,[e[0]||(e[0]=t("span",{class:"info-label"},"Número de Entrada:",-1)),t("span",re,m(n.value.ticketNumber),1)]),t("div",oe,[e[1]||(e[1]=t("span",{class:"info-label"},"Película:",-1)),t("span",de,m(n.value.movieTitle),1)]),t("div",ce,[e[2]||(e[2]=t("span",{class:"info-label"},"Fecha:",-1)),t("span",ue,m(n.value.screeningDate),1)]),t("div",me,[e[3]||(e[3]=t("span",{class:"info-label"},"Hora:",-1)),t("span",pe,m(n.value.screeningTime),1)]),t("div",ve,[e[4]||(e[4]=t("span",{class:"info-label"},"Asiento:",-1)),t("span",ge,m(n.value.seatNumber),1)]),t("div",he,[e[5]||(e[5]=t("span",{class:"info-label"},"Precio:",-1)),t("span",be,m(n.value.price)+" €",1)])])])])):C("",!0),t("div",fe,[e[12]||(e[12]=t("h3",null,"Detalles de la Compra",-1)),t("div",ye,[e[7]||(e[7]=t("span",null,"Número de Orden:",-1)),t("span",_e,m(L.value),1)]),t("div",ke,[e[8]||(e[8]=t("span",null,"ID de Pago:",-1)),t("span",Ne,m(d.value),1)]),t("div",Te,[e[9]||(e[9]=t("span",null,"Fecha de Compra:",-1)),t("span",null,m(z.value),1)]),t("div",we,[e[10]||(e[10]=t("span",null,"Importe Pagado:",-1)),t("span",Ae,m(R.value)+" €",1)]),t("div",Se,[e[11]||(e[11]=t("span",null,"Estado:",-1)),t("span",De,m(O.value),1)])]),t("div",Ce,[e[18]||(e[18]=t("h3",null,"Próximos Pasos",-1)),t("ol",null,[t("li",null,[e[13]||(e[13]=A("Recuerda tu número de entrada: ",-1)),t("strong",null,m(d.value),1)]),e[14]||(e[14]=t("li",null,"Recibirás un email de confirmación con tu comprobante",-1)),e[15]||(e[15]=t("li",null,"Puedes imprimir tu entrada desde el botón de abajo",-1)),e[16]||(e[16]=t("li",null,"Presenta tu entrada en la taquilla del cine",-1)),e[17]||(e[17]=t("li",null,"Llega 15 minutos antes de que comience la función",-1))])]),t("div",Ee,[t("button",{onClick:V,class:"btn btn-primary btn-large",disabled:k.value},[k.value?(b(),f("span",Ie,"Imprimiendo...")):(b(),f("span",xe,"Imprimir Entrada"))],8,Pe),t("button",{onClick:q,class:"btn btn-secondary btn-large"}," Descargar Entrada "),W(i,{to:"/",class:"btn btn-tertiary btn-large"},{default:Y(()=>[...e[19]||(e[19]=[A(" Volver al Inicio ",-1)])]),_:1})]),g.value?(b(),f("p",{key:3,class:G(["print-message",y.value])},m(g.value),3)):C("",!0),e[21]||(e[21]=t("div",{class:"contact-section"},[t("h4",null,"¿Necesitas ayuda?"),t("p",null,[A("Contacta con nosotros en "),t("strong",null,"info@cinea.es")]),t("p",null,[A("Teléfono: "),t("strong",null,"+34 91 123 4567")]),t("p",{class:"hours"},"Lun-Dom: 10:00 - 22:00")],-1))])]),e[22]||(e[22]=t("div",{id:"print-frame",style:{display:"none"}},null,-1))])}}},Oe=Q($e,[["__scopeId","data-v-39420fa6"]]);export{Oe as default};
