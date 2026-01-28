import {ComponentPreview, Previews} from '@react-buddy/ide-toolbox'
import {PaletteTree} from './palette'
import ContactDetails from "../pages/ContactDetails";

const ComponentPreviews = () => {
    return (
        <Previews palette={<PaletteTree/>}>
            <ComponentPreview path="/ContactDetails">
                <ContactDetails/>
            </ComponentPreview>
        </Previews>
    )
}

export default ComponentPreviews