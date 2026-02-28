import{s as j,C as B,r as p,p as D,j as J,b as W,o as h,d as f,e as t,t as c,k as C,g as w,f as Y,w as G,n as K}from"./vendor-d71df2f6.js";import{p as Q}from"./paymentService-ba0bd177.js";import{_ as X}from"./index-4fbb160f.js";const E={async printThermalTicket(n){try{const s=window.open("","","width=400,height=600");if(!s)return console.error("No se pudo abrir la ventana de impresión"),!1;const o=this.generateThermalHTML(n);return s.document.write(o),s.document.close(),s.onload=()=>{s.print(),setTimeout(()=>{s.close()},1e3)},!0}catch(s){return console.error("Error al imprimir:",s),!1}},async printMultipleTickets(n){try{const s=window.open("","","width=400,height=600");if(!s)return console.error("No se pudo abrir la ventana de impresión"),!1;let o=`
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="UTF-8">
          <style>
            ${this.getThermalStyles()}
          </style>
        </head>
        <body>
      `;return n.forEach((u,i)=>{o+=this.generateThermalTicketContent(u),i<n.length-1&&(o+='<div style="page-break-after: always; margin-top: 20px;"></div>')}),o+="</body></html>",s.document.write(o),s.document.close(),s.onload=()=>{s.print(),setTimeout(()=>{s.close()},1e3)},!0}catch(s){return console.error("Error al imprimir múltiples tickets:",s),!1}},generateThermalHTML(n){const s=this.generateThermalTicketContent(n);return`
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
    `},generateThermalTicketContent(n){const o=new Date().toLocaleDateString("es-ES",{day:"2-digit",month:"2-digit",year:"numeric",hour:"2-digit",minute:"2-digit"});return`
      <div class="thermal-ticket">
        <div class="thermal-header">
          <div class="cinema-name">CINEA</div>
          <div class="cinema-subtitle">Cines Independientes</div>
        </div>

        <div class="thermal-divider">═══════════════════════════</div>

        <div class="thermal-section">
          <div class="thermal-label">PELÍCULA:</div>
          <div class="thermal-value bold">${this.truncateText(n.movieTitle||"N/A",32)}</div>
        </div>

        <div class="thermal-section">
          <div class="thermal-row">
            <div class="thermal-col">
              <div class="thermal-label">FECHA:</div>
              <div class="thermal-value">${n.screeningDate||"N/A"}</div>
            </div>
            <div class="thermal-col">
              <div class="thermal-label">HORA:</div>
              <div class="thermal-value">${n.screeningTime||"N/A"}</div>
            </div>
          </div>
        </div>

        <div class="thermal-section">
          <div class="thermal-label">ASIENTO:</div>
          <div class="thermal-value bold">${n.seatNumber||"N/A"}</div>
        </div>

        <div class="thermal-section">
          <div class="thermal-label">ENTRADA:</div>
          <div class="thermal-barcode">${n.ticketNumber||"N/A"}</div>
          <div class="thermal-code-small">${n.ticketNumber||"N/A"}</div>
        </div>

        <div class="thermal-section">
          <div class="thermal-label">PRECIO:</div>
          <div class="thermal-value bold">${n.price||"0.00"} €</div>
        </div>

        <div class="thermal-divider">═══════════════════════════</div>

        <div class="thermal-footer">
          <div class="thermal-small">Impreso: ${o}</div>
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
    `},truncateText(n,s){return n.length<=s?n:n.substring(0,s-3)+"..."},getTicketData(n){const s=localStorage.getItem("tickets");if(s)try{const u=JSON.parse(s).find(i=>i.ticketNumber===n);if(u)return u}catch(o){console.error("Error al leer tickets del localStorage:",o)}return{ticketNumber:n,movieTitle:"Película",screeningDate:new Date().toLocaleDateString("es-ES"),screeningTime:"20:00",seatNumber:"N/A",price:"10.00"}}};const Z={class:"payment-success-page"},ee={class:"container"},te={class:"success-card"},ae={key:0,class:"loading-message"},se={key:1,class:"loading-error"},ne={key:2,class:"desk-assistance"},ie={key:3,class:"ticket-display"},le={class:"ticket-section"},re={class:"info-grid"},oe={class:"info-item"},de={class:"info-value code"},ce={class:"info-item"},ue={class:"info-value"},me={class:"info-item"},pe={class:"info-value"},ve={class:"info-item"},ge={class:"info-value"},be={class:"info-item"},he={class:"info-value"},fe={class:"info-item"},ye={class:"info-value"},ke={class:"purchase-details"},Ne={class:"detail-row"},_e={class:"code"},we={class:"detail-row"},Te={class:"code"},Ae={class:"detail-row"},Se={class:"detail-row"},De={class:"amount"},Ce={class:"detail-row"},Ee={class:"status-badge status-success"},xe={class:"next-steps"},Pe={class:"action-buttons"},Ie=["disabled"],$e={key:0},ze={key:1},Re=["disabled"],Le={__name:"PaymentSuccess",setup(n){const s=j(),o=B(),u=p(""),i=p(null),y=p([]),T=p(!1),x=p(!1),A=p(""),v=p(""),g=p("info"),P=p(null),k=p(null),b=p(!1),$=(a,e)=>{if(!(!a||!e))return e.split(".").reduce((r,l)=>r==null?void 0:r[l],a)},m=(a,e=[])=>{for(const r of e){const l=$(a,r);if(l!=null&&l!=="")return l}return null},z=(a,e="")=>{const r=m(a,["seat_row","seatRow"])||"",l=m(a,["seat_number","seatNumber"])||"",N=m(a,["seat_code","seatCode"])||`${r}${l}`||"N/A";return{ticketNumber:String(m(a,["ticket_number","ticketNumber"])||e||"N/A"),movieTitle:String(m(a,["movie_title","movieTitle"])||"Película"),screeningDate:String(m(a,["screening_date","screeningDate"])||"N/A"),screeningTime:String(m(a,["screening_time","screeningTime"])||"N/A"),seatNumber:N,price:String(m(a,["price","total_price","amount"])||"0.00")}},R=a=>{const e=String(a||"").toLowerCase();return e==="completed"||e==="approved"?"Completado":e==="processing"||e==="pending"?"Procesando":e==="rejected"||e==="declined"?"Rechazado":"Completado"},L=D(()=>{var r,l;const a=((r=k.value)==null?void 0:r.paidAt)||((l=k.value)==null?void 0:l.updatedAt);return(a?new Date(a):new Date).toLocaleDateString("es-ES",{day:"2-digit",month:"2-digit",year:"numeric",hour:"2-digit",minute:"2-digit"})}),I=D(()=>{var a;return((a=P.value)==null?void 0:a.orderNumber)||"N/A"}),O=D(()=>{var e;const a=Number((e=k.value)==null?void 0:e.amount);return Number.isFinite(a)?a.toFixed(2):"No disponible"}),F=D(()=>{var a;return R((a=k.value)==null?void 0:a.status)});J(async()=>{if(u.value=o.query.ticket||o.params.ticket||"N/A",P.value={orderNumber:String(o.query.order||"N/A")},!u.value||u.value==="N/A"){s.push("/");return}await V(),window.scrollTo(0,0)});const M=(a=[])=>{if(a.length)try{const e=localStorage.getItem("tickets"),r=e?JSON.parse(e):[],l=new Map(r.map(d=>[d.ticketNumber,d]));for(const d of a)d!=null&&d.ticketNumber&&l.set(d.ticketNumber,d);localStorage.setItem("tickets",JSON.stringify(Array.from(l.values())))}catch(e){console.warn("No se pudo persistir tickets en localStorage:",e)}},U=async()=>{if(!y.value.length&&!i.value)return;const a=y.value.length?y.value:[i.value];let e=!1;a.length>1?e=await E.printMultipleTickets(a):e=await E.printThermalTicket(a[0]),e?(g.value="success",v.value="Impresión automática iniciada."):(g.value="error",v.value='No se pudo imprimir automáticamente. Usa el botón "Imprimir Entrada".')},V=async()=>{x.value=!0,A.value="",b.value=!1;try{const e=await Q.getPaymentStatus(u.value)||{},r=m(e,["tickets","data.tickets","order.tickets","payment.tickets"]);if(!Array.isArray(r)||r.length===0)throw new Error("Datos de tickets incompletos");const l=r.map(S=>z(S,u.value));y.value=l,i.value=l[0];const d=m(e,["order_number","order.number","data.order_number"]);d&&(P.value={orderNumber:String(d)});const N=m(e,["total_price","amount","payment.amount","data.total_price","data.amount"]),_=Number(N);if(!Number.isFinite(_))throw new Error("Monto no disponible desde backend");k.value={amount:_,status:m(e,["status","payment.status","data.status"])||"completed",paidAt:m(e,["paid_at","payment.paid_at","data.paid_at"])||null,updatedAt:m(e,["updated_at","payment.updated_at","data.updated_at"])||null},M(l),await U()}catch(a){console.error("Error al cargar datos del ticket:",a),A.value="Faltan datos del pago o el backend no respondió. Continúa en ventanilla.",b.value=!0,i.value=null,y.value=[],k.value=null,g.value="error",v.value="Impresión deshabilitada: ve a ventanilla con tu número de orden."}finally{x.value=!1}},q=async()=>{if(b.value){g.value="error",v.value="Impresión deshabilitada: ve a ventanilla con tu número de orden.";return}T.value=!0;try{const a=y.value.length?y.value:[i.value];(a.length>1?await E.printMultipleTickets(a):await E.printThermalTicket(a[0]))?(g.value="success",v.value="Impresión iniciada correctamente."):(g.value="error",v.value="No se pudo completar la impresión. Verifica tu impresora.")}catch(a){console.error("Error durante la impresión:",a),g.value="error",v.value="Error al intentar imprimir. Intenta de nuevo."}finally{T.value=!1}},H=()=>{var d,N,_,S;if(!i.value||b.value)return;const a=`
    <div style="text-align: center; padding: 20px; font-family: Arial, sans-serif;">
      <h1>CINEA - Entrada</h1>
      <p><strong>${((d=i.value)==null?void 0:d.movieTitle)||"Película"}</strong></p>
      <p>Número: ${u.value}</p>
      <p>Fecha: ${((N=i.value)==null?void 0:N.screeningDate)||"N/A"}</p>
      <p>Hora: ${((_=i.value)==null?void 0:_.screeningTime)||"N/A"}</p>
      <p>Asiento: ${((S=i.value)==null?void 0:S.seatNumber)||"N/A"}</p>
    </div>
  `,e=new Blob([a],{type:"text/html"}),r=window.URL.createObjectURL(e),l=document.createElement("a");l.href=r,l.download=`entrada_${u.value}.html`,l.click(),window.URL.revokeObjectURL(r)};return(a,e)=>{const r=W("router-link");return h(),f("div",Z,[t("div",ee,[t("div",te,[e[24]||(e[24]=t("div",{class:"success-header"},[t("div",{class:"success-icon-large icon-success"}),t("h1",null,"¡Pago Exitoso!"),t("p",{class:"success-subtitle"},"Tu compra ha sido procesada correctamente")],-1)),x.value?(h(),f("p",ae,"Cargando información de la compra...")):A.value?(h(),f("p",se,c(A.value),1)):C("",!0),b.value?(h(),f("div",ne,[e[1]||(e[1]=t("h3",null,"Atención en ventanilla requerida",-1)),e[2]||(e[2]=t("p",null,"No pudimos validar todos los datos del pago en línea.",-1)),t("p",null,[e[0]||(e[0]=t("strong",null,"Orden:",-1)),w(" "+c(I.value),1)]),e[3]||(e[3]=t("p",null,"Acércate a ventanilla con este número para finalizar la entrega de entradas.",-1))])):C("",!0),i.value&&!b.value?(h(),f("div",ie,[t("div",le,[e[10]||(e[10]=t("h3",null,"Información de la Entrada",-1)),t("div",re,[t("div",oe,[e[4]||(e[4]=t("span",{class:"info-label"},"Número de Entrada:",-1)),t("span",de,c(i.value.ticketNumber),1)]),t("div",ce,[e[5]||(e[5]=t("span",{class:"info-label"},"Película:",-1)),t("span",ue,c(i.value.movieTitle),1)]),t("div",me,[e[6]||(e[6]=t("span",{class:"info-label"},"Fecha:",-1)),t("span",pe,c(i.value.screeningDate),1)]),t("div",ve,[e[7]||(e[7]=t("span",{class:"info-label"},"Hora:",-1)),t("span",ge,c(i.value.screeningTime),1)]),t("div",be,[e[8]||(e[8]=t("span",{class:"info-label"},"Asiento:",-1)),t("span",he,c(i.value.seatNumber),1)]),t("div",fe,[e[9]||(e[9]=t("span",{class:"info-label"},"Precio:",-1)),t("span",ye,c(i.value.price)+" €",1)])])])])):C("",!0),t("div",ke,[e[16]||(e[16]=t("h3",null,"Detalles de la Compra",-1)),t("div",Ne,[e[11]||(e[11]=t("span",null,"Número de Orden:",-1)),t("span",_e,c(I.value),1)]),t("div",we,[e[12]||(e[12]=t("span",null,"ID de Pago:",-1)),t("span",Te,c(u.value),1)]),t("div",Ae,[e[13]||(e[13]=t("span",null,"Fecha de Compra:",-1)),t("span",null,c(L.value),1)]),t("div",Se,[e[14]||(e[14]=t("span",null,"Importe Pagado:",-1)),t("span",De,c(O.value)+" €",1)]),t("div",Ce,[e[15]||(e[15]=t("span",null,"Estado:",-1)),t("span",Ee,c(F.value),1)])]),t("div",xe,[e[22]||(e[22]=t("h3",null,"Próximos Pasos",-1)),t("ol",null,[t("li",null,[e[17]||(e[17]=w("Recuerda tu número de entrada: ",-1)),t("strong",null,c(u.value),1)]),e[18]||(e[18]=t("li",null,"Recibirás un email de confirmación con tu comprobante",-1)),e[19]||(e[19]=t("li",null,"Puedes imprimir tu entrada desde el botón de abajo",-1)),e[20]||(e[20]=t("li",null,"Presenta tu entrada en la taquilla del cine",-1)),e[21]||(e[21]=t("li",null,"Llega 15 minutos antes de que comience la función",-1))])]),t("div",Pe,[t("button",{onClick:q,class:"btn btn-primary btn-large",disabled:T.value||b.value},[T.value?(h(),f("span",ze,"Imprimiendo...")):(h(),f("span",$e,"Imprimir Entrada"))],8,Ie),t("button",{onClick:H,class:"btn btn-secondary btn-large",disabled:b.value||!i.value}," Descargar Entrada ",8,Re),Y(r,{to:"/",class:"btn btn-tertiary btn-large"},{default:G(()=>[...e[23]||(e[23]=[w(" Volver al Inicio ",-1)])]),_:1})]),v.value?(h(),f("p",{key:4,class:K(["print-message",g.value])},c(v.value),3)):C("",!0),e[25]||(e[25]=t("div",{class:"contact-section"},[t("h4",null,"¿Necesitas ayuda?"),t("p",null,[w("Contacta con nosotros en "),t("strong",null,"info@cinea.es")]),t("p",null,[w("Teléfono: "),t("strong",null,"+34 91 123 4567")]),t("p",{class:"hours"},"Lun-Dom: 10:00 - 22:00")],-1))])]),e[26]||(e[26]=t("div",{id:"print-frame",style:{display:"none"}},null,-1))])}}},Ue=X(Le,[["__scopeId","data-v-4b0aa5f5"]]);export{Ue as default};
