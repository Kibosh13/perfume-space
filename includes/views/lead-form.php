<form class="form" id="leadForm" novalidate>
  <div class="field">
    <label for="lType">Что нужно</label>
    <select class="input" id="lType" name="request_type">
      <option>Забронировать аромат</option>
      <option>Сообщить о поступлении</option>
      <option>Вопрос о заказе или доставке</option>
    </select>
  </div>
  <div class="field"><label for="lName">Имя</label><input class="input" id="lName" name="name" autocomplete="name" required></div>
  <div class="form-row">
    <div class="field"><label for="lPhone">Телефон</label><input class="input" id="lPhone" name="phone" type="tel" autocomplete="tel" placeholder="+7" required></div>
    <div class="field"><label for="lEmail">Почта</label><input class="input" id="lEmail" name="email" type="email" autocomplete="email" required></div>
  </div>
  <div class="field"><label for="lPerfume">Аромат или пожелания</label><input class="input" id="lPerfume" name="perfume_name" placeholder="Необязательно"></div>
  <label class="consent"><input type="checkbox" name="consent" required><span>Согласен на <a href="/soglasie/" target="_blank">обработку персональных данных</a> в соответствии с <a href="/privacy/" target="_blank">политикой конфиденциальности</a></span></label>
  <button type="submit" class="btn btn--primary btn--block">Отправить заявку</button>
</form>
