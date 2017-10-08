# osTicket - Ticket Feedback Plugin

Collects feedback from Ticket Users.

# Not ready yet! Still working out some kinks.. like, it not working kink.. lol

## To install
- Download master [zip](https://github.com/clonemeagain/plugin-feedback/archive/master.zip) and extract into `/include/plugins/rewriter`
- Then Install and enable as per normal osTicket Plugins

## To configure

### Create the form/field
We don't make a custom table/db or anything, feedback is stored in the ticket 
itself. 
So, you must make a Field in the Ticket Details (or any other Form attached to a
ticket). Then tell the Plugin which form and field.

Visit the Admin-panel, select Manage => Forms, choose the `Ticket Details` or any
other Form, and add a field. Choose "Short" text. 

### Tell the plugin about it
Visit the Admin-panel, select Manage => Plugins, choose the `Ticket Feedback` plugin

Select from the available forms.

Enter the name of the field variable. 

### Modify your Email templates to insert the link
The Plugin works by intercepting a call to https://yourdomain.tld/support/index.php?id={ticket-id}&feedback={up|down|meh}
So, you must add that!
Visit the Admin-panel, select Email => Templates, choose the `Response/Reply Template`

In the footer of the template, you'll find the default text:
```
We hope this response has sufficiently answered your questions. If not, please do not send another email. Instead, reply to this email or login to your account for a complete archive of all your support requests and responses.
```
Press the "Show HTML" button in the redactor text-widget to get HTML view, then add after:
```html
<p>How was your support experience?<br />
    <a href="%{recipient.ticket_link}&feedback=up" title="I liked the support!">
    <img src="%{url}/assets/default/images/icons/ok.png">It was good, thanks!</a>&nbsp;
    <a href="%{recipient.ticket_link}&feedback=up" title="I feel neither">
    <img src="%{url}/assets/default/images/icons/alert.png">Indifferent</a>&nbsp;
    <a href="%{recipient.ticket_link}&feedback=up" title="Something went wrong?">
    <img src="%{url}/assets/default/images/icons/error.png">We need to talk...</a>
</p>
```
When you go back to html view, it will render as html showing the text and three links. 
This is normal. The images aren't shown because the links to them aren't converted
until the template is rendered.

Save the template. 

Now write a reply to a ticket, and receive the email sent, it should include something
like this in the footer:

![feedback](https://user-images.githubusercontent.com/5077391/31316559-8911f78e-ac7b-11e7-9a18-3da036b81838.PNG)

## Caveats:
- Assumes osTicket v1.10+ 

