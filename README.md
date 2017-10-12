# osTicket - Ticket Feedback Plugin

Collects feedback from Ticket Users.

## Not ready yet! Still working out some kinks.. 
The variable injection system isn't working great, so you either have to 
install [this pull](https://github.com/osTicket/osTicket/pull/3111) or manually
configure the template text.. hmm.. still clunky.

## To install
- Download master [zip](https://github.com/clonemeagain/plugin-feedback/archive/master.zip) and extract into `/include/plugins/feedback`
- Then Install and enable as per normal osTicket Plugins

## To configure

### Create the form/field
We don't make a custom table/db or anything, feedback is stored in the ticket 
itself. 
So, you must make two Fields in the Ticket Details Form. Then tell the Plugin what 
variables you used for them.

Visit the Admin-panel, select Manage => Forms, choose the `Ticket Details`, and add a field. Choose "Choices" for `Feedback` & Long text for `Comments`
![field-config-screen-1](https://user-images.githubusercontent.com/5077391/31494557-822dcaf2-af9f-11e7-8209-f827a51324cb.PNG)

#### Configure the Feedback Field
![field-config-screen-2-feedback-choice-config-page-1](https://user-images.githubusercontent.com/5077391/31494554-819a298c-af9f-11e7-831f-5e56b24be7ee.PNG)
![field-config-screen-2-feedback-choice-config-page-2](https://user-images.githubusercontent.com/5077391/31494555-81cbb380-af9f-11e7-92e9-f2a111ca1242.PNG)

#### Configure the Comments Field
![field-config-screen-2-comments-config-page-1](https://user-images.githubusercontent.com/5077391/31494558-825e35d4-af9f-11e7-9b4e-c2b9a17aacf4.PNG)
![field-config-screen-2-comments-config-page-2](https://user-images.githubusercontent.com/5077391/31494559-82907742-af9f-11e7-8e89-fdc3de18435a.PNG)

### Tell the plugin the variable names
Visit the Admin-panel, select Manage => Plugins, choose the `Ticket Feedback` plugin
![field-config-screen-3](https://user-images.githubusercontent.com/5077391/31494556-81fb69b8-af9f-11e7-91d3-06202207356b.png)

Enter the names of the feedback & comments variables.

### Configure the Plugin's Textfields
- The headings are configurable depending on the email link clicked, ie: "Good" => "Yay!: We also think it's great!"
"Bad: Oh No! What can we do to make it right?"


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
    <a href="%{recipient.ticket_link}&feedback=up" title="I liked the support!" style="color:green;">
    <img src="%{url}/assets/default/images/icons/ok.png">It was good, thanks!</a>&nbsp;
    <a href="%{recipient.ticket_link}&feedback=meh" title="I feel neither">
    <img src="%{url}/assets/default/images/icons/alert.png">Indifferent</a>&nbsp;
    <a href="%{recipient.ticket_link}&feedback=down" title="Something went wrong?" style="color:red;">
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

# TODO:
- Figure out how to get reporting data out of the forms, for stats etc.
- Send an alert to Agent/Dept-manager on every "We need to talk" level feedback? 